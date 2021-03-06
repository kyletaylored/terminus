<?php

namespace Pantheon\Terminus\DataStore;

use Pantheon\Terminus\Exceptions\TerminusException;

/**
 * Class FileStore
 * @package Pantheon\Terminus\DataStore
 */
class FileStore implements DataStoreInterface
{
    /**
     * @var string The directory to store the data files in.
     */
    protected $directory;

    public function __construct($directory)
    {
        $this->directory = $directory;
    }

    /**
     * Reads retrieves data from the store
     *
     * @param string $key A key
     * @return mixed The value fpr the given key or null.
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    public function get($key)
    {
        $out = null;
        // Read the json encoded value from disk if it exists.
        $path = $this->getFileName($key);
        if (file_exists($path)) {
            $out = file_get_contents($path);
            $out = json_decode($out, true);
        }
        return $out;
    }

    /**
     * Saves a value with the given key
     *
     * @param string $key A key
     * @param mixed $data Data to save to the store
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    public function set($key, $data)
    {
        $path = $this->getFileName($key, true);
        // Prevent categories group+other from reading, writing or executing
        // any files written to the FileStore, for security/privacy.
        // e.g. tokens are cached and could be read by other user accounts
        // on the machine.
        $old = umask(077);
        file_put_contents($path, json_encode($data));
        umask($old);
    }

    /**
     * Checks if a key is in the store
     *
     * @param string $key A key
     * @return bool Whether a value exists with the given key
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    public function has($key)
    {
        $path = $this->getFileName($key);
        return file_exists($path);
    }

    /**
     * Remove value from the store
     *
     * @param string $key A key
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    public function remove($key)
    {
        $path = $this->getFileName($key, true);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Remove all values from the store
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    public function removeAll()
    {
        foreach ($this->keys() as $key) {
            $this->remove($key);
        }
    }

    /**
     * Return a list of all keys in the store.
     * @return array A list of keys
     */
    public function keys()
    {
        $root = $this->directory;
        if (file_exists($root) && is_readable($root)) {
            return array_diff(scandir($root), array('..', '.'));
        }
        return [];
    }

    /**
     * Get a valid file name for the given key.
     * @param string $key The data key to be written or read
     * @return string A file path
     * @throws TerminusException
     */
    protected function getFileName($key, $writable = false)
    {
        $key = $this->cleanKey($key);

        if ($writable) {
            $this->ensureDirectoryWritable();
        }

        if (!$key) {
            throw new TerminusException('Could not save data to a file because it is missing an ID');
        }
        return $this->directory . '/' . $key;
    }

    /**
     * Make the file path safe by whitelisting characters.
     * This is a very naive approach to hashing but in practice this doesn't matter since this is only used for a
     * few already safe keys.
     *
     * @param $key
     * @return mixed
     */
    protected function cleanKey($key)
    {
        return preg_replace('/[^a-zA-Z0-9\-\_\@\.]/', '-', $key);
    }

    /**
     * Check that the directory is writable and create it if we can.
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    protected function ensureDirectoryWritable()
    {
        // Reality check to prevent stomping on the local filesystem if there is something wrong with the config.
        if (!$this->directory) {
            throw new TerminusException('Could not save data to a file because the path setting is mis-configured.');
        }

        $writable = is_dir($this->directory)
            || (!file_exists($this->directory) && @mkdir($this->directory, 0777, true));
        $writable = $writable && is_writable($this->directory);
        if (!$writable) {
            throw new TerminusException(
                'Could not save data to a file because the path {path} cannot be written to.',
                ['path' => $this->directory]
            );
        }
    }
}
