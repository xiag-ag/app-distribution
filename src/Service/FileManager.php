<?php

namespace AppDistributionTool\Service;

use RuntimeException;

if (defined('TESTS_IN_PROGRESS')) {
    // https://github.com/bovigo/vfsStream/wiki/Known-Issues
    function realpath($path)
    {
        return strpos($path, '..') !== false ? '' : $path;
    }
}

/**
 * Class FileManager
 * @package AppDistributionTool\Service
 */
class FileManager
{
    /**
     * @var string
     */
    protected $rootDirectory;

    /**
     * FileManager constructor.
     * @param string $rootDirectory
     */
    public function __construct(string $rootDirectory)
    {
        $this->rootDirectory = substr($rootDirectory, -1) === '/' ? $rootDirectory : $rootDirectory . '/';
    }

    /**
     * @param string $dirName
     *
     * @return string
     */
    public function removeDirectory(string $dirName): string
    {
        if (($path = $this->getFullPath($dirName)) === $this->rootDirectory) {
            throw new RuntimeException('Unable to delete root directory');
        }

        if (!file_exists($path)) {
            return '0';
        }

        if (!preg_match(
            '/' . str_replace('/', '\/', realpath($this->rootDirectory)) . '(.+\/)?(.+)?$/',
            realpath($path)
        )) {
            throw new RuntimeException('Wrong path to file/directory: getting out of root directory');
        }

        return exec('rm -rf ' . $path . '; echo $?');
    }

    /**
     * @param $directory
     *
     * @return bool
     */
    public function createDirectory($directory): bool
    {
        $path = $this->getFullPath($directory);

        return file_exists($path) || (mkdir($path, 0775, true) && is_dir($path));
    }

    /**
     * @param string $fileName
     * @param string $data
     * @param string $mode
     *
     * @return bool
     */
    public function writeToFile(string $fileName, string $data, $mode = 'wb'): bool
    {
        $path = $this->getFullPath($fileName);

        if (!$filePointer = fopen($path, $mode)) {
            throw new RuntimeException('Failed to open file ' . $path);
        }

        if (fwrite($filePointer, $data) === false) {
            throw new RuntimeException('Failed to write to file ' . $path);
        }

        return fclose($filePointer);
    }

    /**
     * @param string $path
     *
     * @return string
     *
     * @throws RuntimeException
     */
    public function getFileContents(string $path): string
    {
        $path = $this->getFullPath($path);
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('Failed to get file ' . $path . ' content');
        }

        return $content;
    }

    /**
     * @param string $path
     *
     * @return string
     *
     * @throws RuntimeException
     */
    protected function getFullPath(string $path): string
    {
        return $this->rootDirectory . (strpos($path, '/') !== 0 ? $path : ltrim($path, '/'));
    }
}
