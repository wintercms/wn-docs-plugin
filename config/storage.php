<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Documentation disk
    |--------------------------------------------------------------------------
    |
    | Specifies the storage disk of the documentation, allowing documentation
    | to be stored on remote storage if required.
    |
    | The disk must be one of the filesystem disks as specified in the
    | filesystems.php configuration file within the application.
    |
    */

    'disk' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Processed documentation path
    |--------------------------------------------------------------------------
    |
    | Defines the path in the storage disk where processed documentation will
    | be stored for retrieval.
    |
    */

    'processedPath' => 'docs/processed',

    /*
    |--------------------------------------------------------------------------
    | Processed documentation path
    |--------------------------------------------------------------------------
    |
    | Defines the path in the storage disk where downloaded remote
    | documentation will be stored and extracted.
    |
    */

    'downloadPath' => 'docs/download'

];
