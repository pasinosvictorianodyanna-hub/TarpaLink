<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
        | Proof artwork: the customer's files, before they are printed.
        |
        | A disk of its own rather than "whatever the default is", because these
        | files are private by rule — they are served only through a route that
        | checks who is asking — and a default disk that later became public
        | would quietly expose every customer's artwork.
        |
        | The root is deliberately the same as `local` so existing proof paths
        | keep resolving. Set PROOF_DISK_DRIVER=s3 (plus AWS_BUCKET and
        | credentials) to move artwork into object storage; the object keys stay
        | `proofs/...`, so nothing in the database changes.
        */
        'proofs' => [
            'driver' => $proofDriver = env('PROOF_DISK_DRIVER', 'local'),
            // `?:` rather than a default argument: an env line left as
            // `PROOF_DISK_ROOT=` resolves to an empty string, and a local disk
            // rooted at "" would write into the project root.
            //
            // On S3 the root is an *object-key prefix*, not a directory. Leaving
            // the local default in place makes every key begin with the absolute
            // path `/var/www/html/storage/app/private`, so a proof written here
            // can never be found by the `proofs/…` path stored on the row. An
            // object store needs no root to keep the private bucket private.
            'root' => $proofDriver === 's3'
                ? ''
                : (env('PROOF_DISK_ROOT') ?: storage_path('app/private')),
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'private',
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
        | Customer artwork: the file the customer sends the shop to print.
        |
        | Its own disk, and its own env keys, because the two directions have
        | different lifetimes — a proof is kept so the shop can prove what was
        | approved, an artwork is kept so a reprint is possible — and a shop may
        | well want them in different buckets.
        |
        | Both default to the proof settings, so an installation that moves to
        | object storage in one line moves both. Set ARTWORK_DISK_DRIVER to split
        | them. Objects land under `artwork/...`, so nothing collides with the
        | `proofs/...` keys.
        */
        'artwork' => [
            'driver' => $artworkDriver = env('ARTWORK_DISK_DRIVER', env('PROOF_DISK_DRIVER', 'local')),
            // Same rule as `proofs`: a local root is a directory, an S3 root is
            // a key prefix. The upload service passes `artwork/…` itself, so
            // prefixing that with storage_path() would change every object key.
            'root' => $artworkDriver === 's3'
                ? ''
                : (env('ARTWORK_DISK_ROOT') ?: (env('PROOF_DISK_ROOT') ?: storage_path('app/private'))),
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'private',
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
