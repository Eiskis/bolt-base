<?php

namespace Bolt\Database;

use Bolt\Application;
use Bolt\Database\Table\ContentType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class IntegrityChecker
{
    /** @var \Bolt\Application */
    private $app;

    /** @var string */
    private $prefix;

    /** @var \Doctrine\DBAL\Schema\Table[] Current tables. */
    private $tables;

    /** @var array Array of callables that produce table definitions. */
    protected $extension_table_generators = [];

    /** @var string */
    protected $integrityCachePath;

    const INTEGRITY_CHECK_INTERVAL    = 1800; // max. validity of a database integrity check, in seconds
    const INTEGRITY_CHECK_TS_FILENAME = 'dbcheck_ts'; // filename for the check timestamp file

    public $tableMap = [];

    public function __construct(Application $app)
    {
        $this->app = $app;

        // Check the table integrity only once per hour, per session. (since it's pretty time-consuming.
        $this->checktimer = 3600;
    }

    /**
     * Invalidate our database check by removing the timestamp file from cache.
     *
     * @return void
     */
    public function invalidate()
    {
        $fileName = $this->getValidityTimestampFilename();

        // delete the cached dbcheck-ts
        if (is_writable($fileName)) {
            unlink($fileName);
        } elseif (file_exists($fileName)) {
            $message = sprintf(
                "The file '%s' exists, but couldn't be removed. Please remove this file manually, and try again.",
                $fileName
            );
            $this->app->abort(Response::HTTP_UNAUTHORIZED, $message);
        }
    }

    /**
     * Set our state as valid by writing the current date/time to the
     * app/cache/dbcheck-ts file.
     *
     * @return void
     */
    public function markValid()
    {
        $timestamp = time();
        file_put_contents($this->getValidityTimestampFilename(), $timestamp);
    }

    /**
     * Check if our state is known valid by comparing app/cache/dbcheck-ts to
     * the current timestamp.
     *
     * @return boolean
     */
    public function isValid()
    {
        if (is_readable($this->getValidityTimestampFilename())) {
            $validityTS = intval(file_get_contents($this->getValidityTimestampFilename()));
        } else {
            $validityTS = 0;
        }

        return ($validityTS >= time() - self::INTEGRITY_CHECK_INTERVAL);
    }

    /**
     * Get an associative array with the bolt_tables tables as Doctrine Table objects.
     *
     * @return \Doctrine\DBAL\Schema\Table[]
     */
    protected function getTableObjects()
    {
        if (!empty($this->tables)) {
            return $this->tables;
        }

        /** @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
        $sm = $this->app['db']->getSchemaManager();

        $this->tables = [];

        foreach ($sm->listTables() as $table) {
            if (strpos($table->getName(), $this->getTablenamePrefix()) === 0) {
                $this->tables[$table->getName()] = $table;
            }
        }

        return $this->tables;
    }

    /**
     * Check if just the users table is present.
     *
     * @return boolean
     */
    public function checkUserTableIntegrity()
    {
        $tables = $this->getTableObjects();

        // Check the users table.
        if (!isset($tables[$this->getTablename('users')])) {
            return false;
        }

        return true;
    }

    /**
     * Check if all required tables and columns are present in the DB.
     *
     * @param boolean         $hinting     Return hints if true
     * @param LoggerInterface $debugLogger Debug logger
     *
     * @return IntegrityCheckerResponse
     */
    public function checkTablesIntegrity($hinting = false, LoggerInterface $debugLogger = null)
    {
        $response = new IntegrityCheckerResponse($hinting);
        $comparator = new Comparator();
        $currentTables = $this->getTableObjects();
        $tables = $this->getTablesSchema();
        $valid = true;

        /** @var $table Table */
        foreach ($tables as $table) {
            $tableName = $table->getName();

            // Create the users table.
            if (!isset($currentTables[$tableName])) {
                $response->addTitle($tableName, sprintf('Table `%s` is not present.', $tableName));
            } else {
                $diff = $comparator->diffTable($currentTables[$tableName], $table);
                if ($diff && $details = $this->app['db']->getDatabasePlatform()->getAlterTableSQL($diff)) {
                    $response->addTitle($tableName, sprintf('Table `%s` is not the correct schema:', $tableName));
                    $response->checkDiff($tableName, $this->cleanupTableDiff($diff));

                    // For debugging we keep the diffs
                    $response->addDiffDetail($details);
                }
            }

            // If a table still has messages, we want to unset the valid state
            $valid = !$response->hasResponses();

            // If we were passed in a debug logger, log the diffs
            if ($debugLogger !== null) {
                foreach ($response->getDiffDetails() as $diff) {
                    $debugLogger->info('Database update required', $diff);
                }
            }
        }

        // If there were no messages, update the timer, so we don't check it again.
        // If there _are_ messages, keep checking until it's fixed.
        if ($valid) {
            $this->markValid();
        }

        return $response;
    }

    /**
     * Determine if we need to check the table integrity. Do this only once per
     * hour, per session, since it's pretty time consuming.
     *
     * @return boolean TRUE if a check is needed
     */
    public function needsCheck()
    {
        return !$this->isValid();
    }

    /**
     * Check if there are pending updates to the tables.
     *
     * @return boolean
     */
    public function needsUpdate()
    {
        $response = $this->checkTablesIntegrity();

        return $response->hasResponses() ? true : false;
    }

    /**
     * Check and repair tables.
     *
     * @return IntegrityCheckerResponse
     */
    public function repairTables()
    {
        // When repairing tables we want to start with an empty flashbag. Otherwise we get another
        // 'repair your DB'-notice, right after we're done repairing.
        $this->app['logger.flash']->clear();

        $response = new IntegrityCheckerResponse();
        $currentTables = $this->getTableObjects();
        /** @var $schemaManager AbstractSchemaManager */
        $schemaManager = $this->app['db']->getSchemaManager();
        $comparator = new Comparator();
        $tables = $this->getTablesSchema();

        /** @var $table Table */
        foreach ($tables as $table) {
            $tableName = $table->getName();

            // Create the users table.
            if (!isset($currentTables[$tableName])) {

                /** @var $platform AbstractPlatform */
                $platform = $this->app['db']->getDatabasePlatform();
                $queries = $platform->getCreateTableSQL($table);
                foreach ($queries as $query) {
                    $this->app['db']->query($query);
                }

                $response->addTitle($tableName, sprintf('Created table `%s`.', $tableName));
            } else {
                $diff = $comparator->diffTable($currentTables[$tableName], $table);
                if ($diff) {
                    $diff = $this->cleanupTableDiff($diff);
                    // diff may be just deleted columns which we have reset above
                    // only exec and add output if does really alter anything
                    if ($this->app['db']->getDatabasePlatform()->getAlterTableSQL($diff)) {
                        $schemaManager->alterTable($diff);
                        $response->addTitle($tableName, sprintf('Updated `%s` table to match current schema.', $tableName));
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Cleanup a table diff, remove changes we want to keep or fix platform
     * specific issues.
     *
     * @param TableDiff $diff
     *
     * @return \Doctrine\DBAL\Schema\TableDiff
     */
    protected function cleanupTableDiff(TableDiff $diff)
    {
        $baseTables = $this->getBoltTablesNames();

        // Work around reserved column name removal
        if ($diff->fromTable->getName() === $this->getTablename('cron')) {
            foreach ($diff->renamedColumns as $key => $col) {
                if ($col->getName() === 'interim') {
                    $diff->addedColumns[] = $col;
                    unset($diff->renamedColumns[$key]);
                }
            }
        }

        if (!in_array($diff->fromTable->getName(), $baseTables)) {
            // we don't remove fields from contenttype tables to prevent accidental data removal
            $diff->removedColumns = [];
        }

        return $diff;
    }

    /**
     * Get a merged array of tables.
     *
     * @return \Doctrine\DBAL\Schema\Table[]
     */
    public function getTablesSchema()
    {
        $schema = new Schema();

        return array_merge(
            $this->getBoltTablesSchema($schema),
            $this->getContentTypeTablesSchema($schema),
            $this->getExtensionTablesSchema($schema)
        );
    }

    /**
     * This method allows extensions to register their own tables.
     *
     * @param Callable $generator A generator function that takes the Schema
     *                            instance and returns a table or an array of tables.
     */
    public function registerExtensionTable($generator)
    {
        $this->extension_table_generators[] = $generator;
    }

    /**
     * Get all the registered extension tables.
     *
     * @param Schema $schema
     *
     * @return \Doctrine\DBAL\Schema\Table[]
     */
    protected function getExtensionTablesSchema(Schema $schema)
    {
        $tables = [];
        foreach ($this->extension_table_generators as $generator) {
            $table = call_user_func($generator, $schema);
            // We need to be prepared for generators returning a single table,
            // as well as generators returning an array of tables.
            if (is_array($table)) {
                foreach ($table as $t) {
                    $tables[] = $t;
                }
            } else {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    /**
     * Get an array of Bolt's internal tables
     *
     * @return \Doctrine\DBAL\Schema\Table[]
     */
    protected function getBoltTablesNames()
    {
        $baseTables = [];
        /** @var $table Table */
        foreach ($this->getBoltTablesSchema(new Schema()) as $table) {
            $baseTables[] = $table->getName();
        }

        return $baseTables;
    }

    /**
     * @param Schema $schema
     *
     * @return \Doctrine\DBAL\Schema\Table[]
     */
    protected function getBoltTablesSchema(Schema $schema)
    {
        $tables = [];
        foreach ($this->app['integritychecker.tables']->keys() as $name) {
            $tables[] = $this->app['integritychecker.tables'][$name]->buildTable($schema, $this->getTablename($name));
        }

        return $tables;
    }

    /**
     * @param Schema $schema
     *
     * @return \Doctrine\DBAL\Schema\Table[]
     */
    protected function getContentTypeTablesSchema(Schema $schema)
    {
        $tables = [];

        // Now, iterate over the contenttypes, and create the tables if they don't exist.
        foreach ($this->app['config']->get('contenttypes') as $contenttype) {
            $tablename = $this->getTablename($contenttype['tablename']);
            $this->mapTableName($tablename, $contenttype['tablename']);

            $tableObj = new ContentType();
            $myTable = $tableObj->buildTable($schema, $tablename);

            // Check if all the fields are present in the DB.
            foreach ($contenttype['fields'] as $field => $values) {

                /** @var \Doctrine\DBAL\Platforms\Keywords\KeywordList $reservedList */
                $reservedList = $this->app['db']->getDatabasePlatform()->getReservedKeywordsList();
                if ($reservedList->isKeyword($field)) {
                    $error = sprintf(
                        "You're using '%s' as a field name, but that is a reserved word in %s. Please fix it, and refresh this page.",
                        $field,
                        $this->app['db']->getDatabasePlatform()->getName()
                    );
                    $this->app['logger.flash']->error($error);
                    continue;
                }

                // Add the contenttype's specific fields
                $this->addCustomContentTypeFields($myTable, $field, $values['type']);

                if (isset($values['index']) && $values['index'] == 'true') {
                    $myTable->addIndex([$field]);
                }
            }
            $tables[] = $myTable;
        }

        return $tables;
    }

    /**
     * Add the contenttype's specific fields.
     *
     * @param \Doctrine\DBAL\Schema\Table $table
     * @param string                      $fieldName
     * @param string                      $type
     */
    private function addCustomContentTypeFields(Table $table, $fieldName, $type)
    {
        switch ($type) {
            case 'text':
            case 'templateselect':
            case 'file':
                $table->addColumn($fieldName, 'string', ['length' => 256, 'default' => '']);
                break;
            case 'float':
                $table->addColumn($fieldName, 'float', ['default' => 0]);
                break;
            case 'number': // deprecated.
                $table->addColumn($fieldName, 'decimal', ['precision' => '18', 'scale' => '9', 'default' => 0]);
                break;
            case 'integer':
                $table->addColumn($fieldName, 'integer', ['default' => 0]);
                break;
            case 'checkbox':
                $table->addColumn($fieldName, 'boolean', ['default' => 0]);
                break;
            case 'html':
            case 'textarea':
            case 'image':
            case 'video':
            case 'markdown':
            case 'geolocation':
            case 'filelist':
            case 'imagelist':
            case 'select':
                $table->addColumn($fieldName, 'text', ['default' => $this->getTextDefault()]);
                break;
            case 'datetime':
                $table->addColumn($fieldName, 'datetime', ['notnull' => false]);
                break;
            case 'date':
                $table->addColumn($fieldName, 'date', ['notnull' => false]);
                break;
            case 'slug':
                // Only additional slug fields will be added. If it's the
                // default slug, skip it instead.
                if ($fieldName != 'slug') {
                    $table->addColumn($fieldName, 'string', ['length' => 128, 'notnull' => false, 'default' => '']);
                }
                break;
            case 'id':
            case 'datecreated':
            case 'datechanged':
            case 'datepublish':
            case 'datedepublish':
            case 'username':
            case 'status':
            case 'ownerid':
                // These are the default columns. Don't try to add these.
                break;
            default:
                if ($handler = $this->app['config']->getFields()->getField($type)) {
                    /** @var $handler \Bolt\Field\FieldInterface */
                    $table->addColumn($fieldName, $handler->getStorageType(), $handler->getStorageOptions());
                }
        }
    }

    /**
     * Get the tablename with prefix from a given $name.
     *
     * @param $name
     *
     * @return string
     */
    public function getTablename($name)
    {
        $name = str_replace('-', '_', $this->app['slugify']->slugify($name));
        $tablename = sprintf('%s%s', $this->getTablenamePrefix(), $name);

        return $tablename;
    }

    /**
     * Get the tablename prefix
     *
     * @return string
     */
    protected function getTablenamePrefix()
    {
        if ($this->prefix !== null) {
            return $this->prefix;
        }

        $this->prefix = $this->app['config']->get('general/database/prefix', 'bolt_');

        // Make sure prefix ends in '_'. Prefixes without '_' are lame.
        if ($this->prefix[strlen($this->prefix) - 1] != '_') {
            $this->prefix .= '_';
        }

        return $this->prefix;
    }

    /**
     * Default value for TEXT fields, differs per platform.
     *
     * @return string|null
     */
    private function getTextDefault()
    {
        $platform = $this->app['db']->getDatabasePlatform();
        if ($platform instanceof SqlitePlatform || $platform instanceof PostgreSqlPlatform) {
            return '';
        }

        return null;
    }

    /**
     * Get the 'validity' timestamp's file name.
     *
     * @return string
     */
    private function getValidityTimestampFilename()
    {
        if (empty($this->integrityCachePath)) {
            $this->integrityCachePath = $this->app['resources']->getPath('cache');
        }

        return $this->integrityCachePath . '/' . self::INTEGRITY_CHECK_TS_FILENAME;
    }

    /**
     * Map a table name's value.
     *
     * @param string $from
     * @param string $to
     */
    protected function mapTableName($from, $to)
    {
        $this->tableMap[$from] = $to;
    }

    /**
     * Get the stored table name key.
     *
     * @param string $table
     *
     * @return string
     */
    public function getKeyForTable($table)
    {
        return $this->tableMap[$table];
    }
}
