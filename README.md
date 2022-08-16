# Applications distribution tool

## Configuration

Set max file size in nginx conf: lines 5 and 48.

You might want to check out the [`src/config/application.php`](src/config/application.php)

In order to distribute apps to Apple devices, you need also valid HTTPS certificate, so Apple Devices won't refuse to install apps from it. Easiest to use is certbot in conjunction with nginx or traefik.

## Run with docker-compose

```
docker-compose up -d
```

## How to use

* `/upload` used to upload apps. Fill in all the fields and pick a file to upload (we use `ipa`/`apk`/`dmg`/`zip` files). Once uploaded, you will be redirected to the hosted page. Pro tip: release notes is a simple markdown flavor, so you can use links, images, etc.
* `/udid` to help with Apple device UDID discovery. Follow the steps to install the profile (will be automatically removed after installation).
* `/all-apps` can be used to see the full list of apps.

## Advanced topics

It is often useful to have autoformatting of task tracker links, person mentions, etc. This can be made with simple [`src/config/middleware.php`](src/config/middleware.php) file, just replace it with your custom implementation using docker volume mount and you're good. The file is as simple as a single php function. Otherwise you can achieve same thing if you already provide ready-made markdown in the Release Notes.

## Folder structure

Here be dragons! It is advised to use upload function for everything. However you can hack the tool to your need since it is mostly a static storage.

- Save applications to `/apps`.

- One folder for one applications group `/apps/{appGroup}}`. Using only for filter.

- One folder for one application `/apps/{appGroup}/{appName}`. App may contains `app.md` with application description.

- App must have at least one release `/apps/{appGroup}/{appName}/{releaseVersion}`.
    * Release may contain `release.md` with release notes.
    * Release must contain one of `*.apk`, `manifest.plist` or ending with '__' files. Suitable files
    names examples: 
       - manifest.plist
       - new-app.apk
       - archived_app__.zip

- Available filters by application name and version
    * `https://apps.company.name/{appGroup}/{appName}/{releaseVersion}`
    * `https://apps.company.name/{appGroup}/{appName}/`
    * `https://apps.company.name/{appGroup}/`

- Cache of scanned apps for 24 hours in `/cache` dir
