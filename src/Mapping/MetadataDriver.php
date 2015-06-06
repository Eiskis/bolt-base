<?php

namespace Bolt\Mapping;

use Bolt\Database\IntegrityChecker;
use Bolt\Mapping\ClassMetadata as BoltClassMetadata;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\DBAL\Schema\Table;

/**
 * This is a Bolt specific metadata driver that provides mapping information
 * for the internal and user-defined schemas. To do this it takes in the constructor,
 * an instance of IntegrityChecker and uses this to read in the schema.
 *
 * @author Ross Riley <riley.ross@gmail.com>
 */
class MetadataDriver implements MappingDriver
{
    /** @var IntegrityChecker */
    protected $integrityChecker;
    /** @var array */
    protected $contenttypes;
    /** @var array taxonomy configuration */
    protected $taxonomies;
    /** @var array metadata mappings */
    protected $metadata;

    /** @var array */
    protected $defaultAliases = [
        'bolt_authtoken'  => 'Bolt\Entity\Authtoken',
        'bolt_cron'       => 'Bolt\Entity\Cron',
        'bolt_log'        => 'Bolt\Entity\Log',
        'bolt_log_change' => 'Bolt\Entity\LogChange',
        'bolt_log_system' => 'Bolt\Entity\LogSystem',
        'bolt_relations'  => 'Bolt\Entity\Relations',
        'bolt_taxonomy'   => 'Bolt\Entity\Taxonomy',
        'bolt_users'      => 'Bolt\Entity\Users'
    ];

    /** @var array */
    protected $typemap;
    /** @var array */
    protected $aliases = [];

    /**
     *  Keeps a reference of which metadata is not mapped to
     *  a specific entity.
     *
     *  @var array $unmapped
     */
    protected $unmapped;

    /** @var string A default entity for any table not matched */
    protected $fallbackEntity = 'Bolt\Entity\Content';
    /** @var boolean */
    protected $initialized = false;

    /**
     * Constructor.
     *
     * @param IntegrityChecker $integrityChecker
     * @param array            $contenttypes
     * @param array            $taxonomies
     * @param array            $typemap
     */
    public function __construct(IntegrityChecker $integrityChecker, array $contenttypes, array $taxonomies, array $typemap)
    {
        $this->integrityChecker = $integrityChecker;
        $this->contenttypes = $contenttypes;
        $this->taxonomies = $taxonomies;
        $this->typemap = $typemap;
    }

    /**
     * Reads the schema from IntegrityChecker and creates mapping data
     */
    public function initialize()
    {
        $this->initializeShortAliases();
        foreach ($this->integrityChecker->getTablesSchema() as $table) {
            $this->loadMetadataForTable($table);
        }
        $this->initialized = true;
    }

    /**
     * Setup some short aliases so non prefixed keys can be used to get metadata
     */
    public function initializeShortAliases()
    {
        foreach ($this->integrityChecker->getTablesSchema() as $table) {
            $this->aliases[$this->integrityChecker->getKeyForTable($table->getName())] = $table->getName();
        }
    }

    /**
     * Getter for aliases
     *
     * @return array
     */
    public function getAliases()
    {
        return $this->aliases;
    }

    /**
     * Method will try to find an entity class name to handle data,
     * alternatively falling back to $this->fallbackEntity
     *
     * @param string $alias
     *
     * @return string Fully Qualified Class Name
     */
    public function resolveClassName($alias)
    {
        if (class_exists($alias)) {
            return $alias;
        }

        if (array_key_exists($alias, $this->aliases)) {
            $class = $this->aliases[$alias];
            if (class_exists($class)) {
                return $class;
            }
        }

        return $this->fallbackEntity;
    }

    /**
     * Load the metadata for a table.
     *
     * @param Table $table
     */
    protected function loadMetadataForTable(Table $table)
    {
        $tblName = $table->getName();

        if (isset($this->defaultAliases[$tblName])) {
            $className = $this->defaultAliases[$tblName];
        } else {
            $className = $tblName;
            $this->unmapped[] = $tblName;
        }

        $contentKey = $this->integrityChecker->getKeyForTable($tblName);

        $this->metadata[$className] = [];
        $this->metadata[$className]['identifier'] = $table->getPrimaryKey();
        $this->metadata[$className]['table'] = $table->getName();
        $this->metadata[$className]['boltname'] = $contentKey;
        foreach ($table->getColumns() as $colName => $column) {
            $mapping = [
                'fieldname'        => $colName,
                'type'             => $column->getType()->getName(),
                'fieldtype'        => $this->getFieldTypeFor($table->getName(), $column),
                'length'           => $column->getLength(),
                'nullable'         => $column->getNotnull(),
                'platformOptions'  => $column->getPlatformOptions(),
                'precision'        => $column->getPrecision(),
                'scale'            => $column->getScale(),
                'default'          => $column->getDefault(),
                'columnDefinition' => $column->getColumnDefinition(),
                'autoincrement'    => $column->getAutoincrement(),
            ];

            $this->metadata[$className]['fields'][$colName] = $mapping;
            $this->metadata[$className]['fields'][$colName]['data'] = $this->contenttypes[$contentKey]['fields'][$colName];
        }

        // This loop checks the contenttypes definition for any non-db fields and adds them.
        if ($contentKey) {
            $this->setRelations($contentKey, $className, $table);
            $this->setTaxonomies($contentKey, $className, $table);
        }

        foreach ($this->getAliases() as $alias => $table) {
            if (array_key_exists($table, $this->metadata)) {
                $this->metadata[$alias] = $this->metadata[$table];
            }
        }
    }

    /**
     * Set the relationship.
     *
     * @param string $contentKey
     * @param string $className
     * @param Table  $table
     */
    public function setRelations($contentKey, $className, $table)
    {
        if (!isset($this->contenttypes[$contentKey]['relations'])) {
            return;
        }
        foreach ($this->contenttypes[$contentKey]['relations'] as $key => $data) {
            if (isset($data['alias'])) {
                $relationKey = $data['alias'];
            } else {
                $relationKey = $key;
            }

            $mapping = [
                'fieldname' => $relationKey,
                'type'      => 'null',
                'fieldtype' => $this->typemap['relation'],
                'entity'    => $this->resolveClassName($relationKey),
                'target'    => $this->integrityChecker->getTableName('relations'),
            ];

            $this->metadata[$className]['fields'][$relationKey] = $mapping;
            $this->metadata[$className]['fields'][$relationKey]['data'] = $data;
        }
    }

    /**
     * Set the taxonomy.
     *
     * @param string $contentKey
     * @param string $className
     * @param Table  $table
     */
    public function setTaxonomies($contentKey, $className, $table)
    {
        if (!isset($this->contenttypes[$contentKey]['taxonomy'])) {
            return;
        }

        foreach ($this->contenttypes[$contentKey]['taxonomy'] as $taxonomytype) {
            $taxonomyConfig = $this->taxonomies[$taxonomytype];

            if (isset($taxonomyConfig['alias'])) {
                $taxonomy = $taxonomyConfig['alias'];
            } else {
                $taxonomy = $taxonomytype;
            }

            $mapping = [
                'fieldname' => $taxonomy,
                'type'      => 'null',
                'fieldtype' => $this->typemap['taxonomy'],
                'entity'    => $this->resolveClassName($relationKey),
                'target'    => $this->integrityChecker->getTableName('taxonomy'),
            ];

            $this->metadata[$className]['fields'][$taxonomy] = $mapping;
            $this->metadata[$className]['fields'][$taxonomy]['data'] = $taxonomyConfig;
        }
    }

    /**
     * @inheritdoc
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata = null)
    {
        if (null === $metadata) {
            $fullClassName = $this->resolveClassName($className);
            $metadata = new BoltClassMetadata($fullClassName);
        }
        if (!$this->initialized) {
            $this->initialize();
        }
        if (array_key_exists($className, $this->metadata)) {
            $data = $this->metadata[$className];
            $metadata->setTableName($data['table']);
            $metadata->setIdentifier($data['identifier']);
            $metadata->setFieldMappings($data['fields']);
            $metadata->setBoltName($data['boltname']);
            return $metadata;
        } else {
            throw new \Exception("Attempted to load mapping data for unmapped class $className");
        }
    }

    /**
     * Get the field type for a given column.
     *
     * @param string                       $name
     * @param \Doctrine\DBAL\Schema\Column $column
     */
    protected function getFieldTypeFor($name, $column)
    {
        $contentKey = $this->integrityChecker->getKeyForTable($name);
        if ($contentKey && isset($this->contenttypes[$contentKey][$column->getName()])) {
            $type = $this->contenttypes[$contentKey]['fields'][$column->getName()]['type'];
        } elseif ($column->getType()) {
            $type = get_class($column->getType());
        }

        if (isset($this->typemap[$type])) {
            $type = new $this->typemap[$type];
        } else {
            $type = new $this->typemap['text'];
        }

        return $type;
    }

    /**
     * @inheritdoc
     */
    public function getAllClassNames()
    {
        return array_keys($this->metadata);
    }

    /**
     * Gets a list of tables that are not mapped to specific entities.
     *
     * @return array
     */
    public function getUnmapped()
    {
        return $this->unmapped;
    }

    /**
     * Adds an alias mapping from an internal name to a Fully Qualified Entity.
     *
     * @param string $alias.
     * @param string $entity.
     *
     * @return void.
     */
    public function setDefaultAlias($alias, $entity)
    {
        $this->defaultAliases[$alias] = $entity;
    }

    /**
     * Returns the metadata for a given class name.
     *
     * @param string $className
     *
     * @return ClassMetadata|false The class metadata.
     */
    public function getClassMetadata($className)
    {
        if (!$this->initialized) {
            $this->initialize();
        }
        if (array_key_exists($className, $this->metadata)) {
            return $this->metadata[$className];
        }

        return false;
    }

    /**
     * Not implemented, always returns false.
     *
     * @param string $className
     *
     * @return boolean
     */
    public function isTransient($className)
    {
        return false;
    }
}
