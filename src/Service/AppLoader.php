<?php

namespace AppDistributionTool\Service;

/**
 * Class AppLoader
 * @package AppDistributionTool\Controller
 */
class AppLoader
{
    public const VERSION_RELEASE_NOTES_FILE_NAME = 'release.md';
    public const IOS_MANIFEST_FILE_NAME = 'manifest.plist';
    public const APP_INFO_FILE_NAME = 'app.md';

    /**
     * @param CacheManager $cacheManager
     * @param string $appsDir
     *
     * @return mixed
     *
     * @throws \JsonException
     */
    public function loadApps(CacheManager $cacheManager, string $appsDir)
    {
        if ($cacheManager->checkCacheExpired() || !$cacheContent = $cacheManager->getCacheContent()) {
            $content = self::scanGroups($appsDir);

            if ($cacheManager->updateCache($content)) {
                return $content;
            }

            throw new \RuntimeException('Failed to update cache');
        }

        return $cacheContent;
    }

    /**
     * @param array $apps
     * @param string $name
     *
     * @return array
     *
     * @throws \Exception
     */
    public function filterApp(array $apps, string $name): array
    {
        $apps = array_filter($apps, function ($app) use ($name) {
            $values = [
                !empty($app['name']) ? strtolower($app['name']) : null,
                !empty($app['groupName']) ? strtolower($app['groupName']) : null,
            ];

            return in_array(strtolower($name), $values);
        });

        if (count($apps) !== 1) {
            throw new \Exception('App not found');
        }

        return $apps;
    }

    /**
     * @param array $app
     * @param string $version
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function filterVersion(array $app, string $version)
    {
        $versions = array_filter($app['versions'], function ($data) use ($version) {
            return $data['number'] === $version;
        });

        if (count($versions) === 0) {
            throw new \Exception('Version not found');
        }

        return $versions;
    }

    /**
     * @param string $appsDir
     *
     * @return array
     */
    protected static function scanGroups(string $appsDir): array
    {
        return self::scanDirectory($appsDir, function($dir, $entry, &$entries) {
            $apps = self::scanApps($dir);

            if (count($apps) > 0) {
                $entries[strtolower($entry)] = [
                    'groupName' => $entry,
                    'apps' => $apps
                ];
            }
        });
    }

    /**
     * @param string $groupDir
     *
     * @return array
     */
    protected static function scanApps(string $groupDir): array
    {
        $apps = self::scanDirectory($groupDir, function($dir, $entry, &$entries) {
            $versions = self::scanVersions($dir);

            if (count($versions) > 0) {
                $descriptionFile = $dir . '/' . self::APP_INFO_FILE_NAME;

                $entries[strtolower($entry)] = [
                    'name' => $entry,
                    'description' => file_exists($descriptionFile) ? file_get_contents($descriptionFile) : '',
                    'versions' => $versions,
                ];
            }
        });

        krsort($apps);

        return $apps;
    }

    /**
     * @param string $applicationDir
     *
     * @return array
     */
    protected static function scanVersions(string $applicationDir): array
    {
        $versions = self::scanDirectory($applicationDir, function($dir, $entry, &$entries) {
            $descriptionFile = $dir . '/' . self::VERSION_RELEASE_NOTES_FILE_NAME;
            $manifestExists = file_exists($dir . '/' . self::IOS_MANIFEST_FILE_NAME);

            if ($manifestExists) {
                $link = AppLoader::IOS_MANIFEST_FILE_NAME;
            } else {
                $link = self::file($dir, '/(.*)\.apk$/Ui') ?: self::file($dir, '/(.*)__\./Ui');
            }

            $entries[$entry] = [
                'number' => $entry,
                'description' => file_exists($descriptionFile) ? file_get_contents($descriptionFile) : '',
                'type' => $manifestExists ? 'ios' : 'android',
                'link' => $link,
            ];
        });

        uksort($versions, 'version_compare');

        return array_reverse($versions);
    }

    /**
     * @param string $directoryPath
     * @param callable $callback
     *
     * @return array
     */
    protected static function scanDirectory(string $directoryPath, callable $callback): array
    {
        $entries = [];

        if ($handle = opendir($directoryPath)) {
            while (false !== ($entry = readdir($handle))) {
                $dir = $directoryPath . '/' . $entry;
                if ($entry !== '.' && $entry !== '..' && is_dir($dir)) {
                    $callback($dir, $entry, $entries);
                }
            }
            closedir($handle);
        }

        return $entries;
    }

    /**
     * @param string $dir
     * @param string $regexp
     * @return string|null
     */
    protected static function file(string $dir, string $regexp): ?string
    {
        if ($handle = opendir($dir)) {
            while (false !== ($entry = readdir($handle))) {
                if (preg_match($regexp, $entry)) {
                    closedir($handle);
                    return $entry;
                }
            }
            closedir($handle);
        }

        return null;
    }
}
