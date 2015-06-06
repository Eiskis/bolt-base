<?php

namespace Bolt\Configuration;

use Bolt\Exception\FilesystemException;
use League\Flysystem\File;
use Silex;
use Symfony\Component\Yaml\Parser;

/**
 * Allows (simple) modifications of Bolt .yml files.
 *
 * @author Bob den Otter <bob@twokings.nl>
 **/
class YamlUpdater
{
    /** @var Parser */
    private $parser;
    /** @var integer "File pointer". Basically used as offset for searching. */
    private $pointer = 0;
    /** @var integer Number of lines in the file. */
    private $lines = 0;
    /** @var array Contains a line of the file per index. */
    private $yaml = [];
    /** @var File */
    private $file;

    /**
     * Creates an updater for the given file.
     *
     * @param \Silex\Application $app
     * @param string             $filename The file to modify
     */
    public function __construct(Silex\Application $app, $filename = '')
    {
        $this->changed = false;
        $this->file = $app['filesystem']->get('config://' . $filename, new File());
        $this->parser = new Parser();

        // Get the contents of the file
        $this->yaml = $this->file->read();

        // Check that the read-in YAML is valid
        $this->parser->parse($this->yaml, true, true);

        // Create a searchable array
        $this->yaml = explode("\n", $this->yaml);

        // Track the number of lines we have
        $this->lines = count($this->yaml);
    }

    /**
     * Get a value from the yml. return an array with info.
     *
     * @param string $key
     *
     * @return boolean|array
     */
    public function get($key)
    {
        // resets pointer
        $this->pointer = 0;
        $result = false;
        $keyparts = explode("/", $key);

        foreach ($keyparts as $count => $keypart) {
            $result = $this->find($keypart, $count);
        }

        if ($result !== false) {
            return $this->parseline($result);
        } else {
            return false;
        }
    }

    /**
     * Find a specific part of the key, starting from $this->pointer.
     *
     * @param string  $keypart
     * @param integer $indent
     *
     * @return boolean|integer
     */
    private function find($keypart, $indent = 0)
    {
        while ($this->pointer <= $this->lines) {
            $needle = substr('                                      ', 0, 2 * $indent) . $keypart . ':';
            if (isset($this->yaml[$this->pointer]) && strpos($this->yaml[$this->pointer], $needle) === 0) {
                return $this->pointer;
            }
            $this->pointer++;
        }

        // Pointer is past end of file.
        return false;
    }

    /**
     * Parse a specific line-number into its key, value parts, with the used indentation.
     *
     * @param integer $line
     *
     * @return array
     */
    private function parseline($line)
    {
        preg_match_all('/(\s*)([a-z0-9_-]+):(\s)?(.*)/', $this->yaml[$line], $match);

        return [
            'line'        => $line,
            'indentation' => $match[1][0],
            'key'         => $match[2][0],
            'value'       => $match[4][0]
        ];
    }

    /**
     * Change a key into a new value. Save .yml afterwards.
     *
     * @param string  $key        YAML key to modify
     * @param mixed   $value      New value
     * @param boolean $makebackup Back up the file before commiting changes to it
     *
     * @return boolean
     */
    public function change($key, $value, $makebackup = true)
    {
        $match = $this->get($key);

        // Not found.
        if (!$match) {
            return false;
        }

        $value = $this->prepareValue($value);

        $this->yaml[$match['line']] = sprintf("%s%s: %s\n", $match['indentation'], $match['key'], $value);

        return $this->save($makebackup);
    }

    /**
     * Make sure the value is escaped as a yaml value.
     *
     * array('one', 'two', 'three') => [ one, two, three ]
     * "usin' quotes" => 'usin'' quotes
     *
     * @param string $value
     *
     * @return string
     */
    public function prepareValue($value)
    {
        if (is_array($value)) {
            return "[ " . implode(", ", $value) . " ]";
        }

        if (preg_match('/[^a-z0-9]/i', $value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }

        return $value;
    }

    /**
     * Save our modified .yml file.
     *
     * @param boolean $makebackup Back up the file before commiting changes to it
     *
     * @throws \Bolt\Exception\FilesystemException
     *
     * @return boolean true if save was successful
     */
    protected function save($makebackup)
    {
        if (!$this->verify()) {
            return false;
        }

        // If we're backing up do it, if we can
        if ($makebackup) {
            $this->backup();
        }

        // Update the YAML file if we can, or throw an error
        if (! $this->file->update($this->yaml)) {
            throw new FilesystemException('Unable to write to file: ' . $this->file->getPath(), FilesystemException::FILE_NOT_WRITEABLE);
        }

        return true;
    }

    /**
     * Verify if the modified YAML is still a valid .yml file, and if we
     * are actually allowed to write and update the current file.
     *
     * @return boolean
     */
    protected function verify()
    {
        if (is_array($this->yaml)) {
            $this->yaml = implode("\n", $this->yaml);
        }

        // This will throw a ParseException If the YAML is not valid
        $this->parser->parse($this->yaml, true, true);

        return true;
    }

    /**
     * Backup the YAML file.
     */
    protected function backup()
    {
        $this->file->copy($this->file->getPath() . '.' . date('Ymd-His'));
    }
}
