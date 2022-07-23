<?php

namespace Nebula\Filesystem;

use Nebula\Exceptions\FilesystemException;

class StorageManager {
    /**
     * The filesystem configuration array.
     *
     * @var array
     */
    public $filesystemConfig;

    /**
     * The storage disk.
     *
     * @var array
     */
    public $disk;

    /**
     * The permissions data.
     *
     * @var array
     */
    protected $permissions = [
        'local' => [
            'file' => [
                'public' => 0664,
                'private' => 0600
            ],
            'folder' => [
                'public' => 0775,
                'private' => 0700
            ]
        ]
    ];

    /**
     * Create a new class instance.
     *
     * @param array $filesystemConfig The authentication configuration array.
     * @param \PDO $dbConnection The PDO connection instance.
     * @return void
     */
    public function __construct($filesystemConfig = null)
    {
        $this->filesystemConfig = $filesystemConfig ?? app()->config['filesystem'];

        // Set the default disk
        $this->disk = $this->filesystemConfig['default'] ?? 'local';
    }

    /**
     * Performs preprocessing logic for Accessors.
     *
     * @return void
     */
    public function accessorPreProcess()
    {
        // Set the default disk
        $this->disk = $this->filesystemConfig['default'];
    }

    /**
     * Sets the disk selection for the storage
     *
     * @param string $disk The name of the disk.
     * @return \Nebula\Filesystem\StorageManager
     *
     * @throws \Nebula\Exceptions\FilesystemException
     */
    public function disk($disk)
    {
        // Check the filesystem config to ensure selection is valid
        if (empty($this->filesystemConfig['disks'][$disk])) {
            throw new FilesystemException("Invalid disk selection for storage manager.", 500);
        }

        $this->disk = $disk;

        return $this;
    }

    /**
     * Indicates whether the file exists.
     *
     * @param string $path The path to the file for deletion.
     * @return bool
     */
    public function exists($path)
    {
        trim('/', $path);
        $filepath = $this->filesystemConfig['disks'][$this->disk]['root'] . '/' . $path;

        // Handle local driver
        if ($this->filesystemConfig['disks'][$this->disk]['driver'] == 'local') {
            return file_exists($filepath);
        }
    }

    /**
     * Returns the URL for a file if it exists.
     *
     * @param string $path The path to the file for deletion.
     * @return bool
     */
    public function url($path)
    {
        trim('/', $path);
        $filepath = $this->filesystemConfig['disks'][$this->disk]['root'] . '/' . $path;

        // Handle local driver
        if ($this->filesystemConfig['disks'][$this->disk]['driver'] == 'local') {
            if (file_exists($filepath)) {
                return $this->filesystemConfig['disks'][$this->disk]['url'] . '/' . $path;
            }
        }

        return false;
    }

    /**
     * Copies an existing file.
     *
     * @param string $path The path to the file for deletion.
     * @param string $copyPath The path of the copied file.
     * @return string
     */
    public function copy($path, $copyPath)
    {
        trim('/', $path);
        $filepath = $this->filesystemConfig['disks'][$this->disk]['root'] . '/' . $path;
        $newFilepath = $this->filesystemConfig['disks'][$this->disk]['root'] . '/' . $copyPath;

        // Handle local driver
        if ($this->filesystemConfig['disks'][$this->disk]['driver'] == 'local') {
            if (file_exists($filepath)) {
                try {
                    copy($filepath, $newFilepath);
                } catch (\Exception $e) {
                    throw new FilesystemException($e->getMessage(), 500);
                }

                $success = true;
            } else {
                throw new FilesystemException("The file to be copied ($filepath) does not exist.", 500);
            }
        }

        return !empty($success) ? $this->url($copyPath) : false;
    }

    /**
     * Moves an existing file to a new location.
     *
     * @param string $path The path to the file for moving.
     * @param string $copyPath The new path of the moved file.
     * @return string
     */
    public function move($path, $movePath)
    {
        trim('/', $path);
        $filepath = $this->filesystemConfig['disks'][$this->disk]['root'] . '/' . $path;
        $newFilepath = $this->filesystemConfig['disks'][$this->disk]['root'] . '/' . $movePath;

        // Handle local driver
        if ($this->filesystemConfig['disks'][$this->disk]['driver'] == 'local') {
            if (file_exists($filepath)) {
                try {
                    rename($filepath, $newFilepath);
                } catch (\Exception $e) {
                    throw new FilesystemException($e->getMessage(), 500);
                }

                $success = true;
            } else {
                throw new FilesystemException("The file to be moved ($filepath) does not exist.", 500);
            }
        }

        return !empty($success) ? $this->url($movePath) : false;
    }

    /**
     * Puts file content at a specific location.
     *
     * @param string $path The path of the new file.
     * @param string $content The content of the new file.
     * @param string $permission The optional permission level of the new file.
     * @return bool
     *
     * @throws \Nebula\Exceptions\FilesystemException
     */
    public function put($path, $content, $permission = '')
    {
        return $this->putContent($path, $content, $permission);
    }

    /**
     * Puts file content at a specific location.
     *
     * @param string $path The path of the new file.
     * @param string $content The content of the new file.
     * @param string $permission The optional permission level of the new file.
     * @return bool
     *
     * @throws \Nebula\Exceptions\FilesystemException
     */
    public function putContent($path, $content, $permission = '')
    {
        // Handle local driver
        if ($this->filesystemConfig['disks'][$this->disk]['driver'] == 'local') {
            $filepath = $this->filesystemConfig['disks'][$this->disk]['root'] . '/' . $path;

            try {
                file_put_contents(
                    $filepath,
                    $content
                );
            } catch (\Exception $e) {
                throw new FilesystemException($e->getMessage(), 500);
            }

            // Handle permissions
            if (!empty($permission)) {
                if (!in_array($permission, ['public', 'private'])) {
                    throw new FilesystemException("Invalid permission selection for 'put' method.", 500);
                }

                chmod(
                    $filepath,
                    $this->permissions[$this->filesystemConfig['disks'][$this->disk]['driver']]['file'][$permission]
                );
            }
        }
    }

    /**
     * Deletes a file.
     *
     * @param string $path The path to the file for deletion.
     * @return bool
     */
    public function delete($path)
    {
        trim('/', $path);
        $filepath = $this->filesystemConfig['disks'][$this->disk]['root'] . '/' . $path;

        // Handle local driver
        if ($this->filesystemConfig['disks'][$this->disk]['driver'] == 'local') {
            return unlink($filepath);
        }

        return false;
    }

    /**
     * Puts a file at a specific location.
     *
     * @param string $path The path of the new file.
     * @param string $fileArray The array of the uploaded file.
     * @param string $permission The optional permission level of the new file.
     * @return string
     */
    public function putFile($path, $fileArray, $permission = '')
    {
        // Handle local driver
        if ($this->filesystemConfig['disks'][$this->disk]['driver'] == 'local') {
            return $this->handleFilePut($path, $fileArray, null, $permission);
        }
    }

    /**
     * Puts a file at a specific location.
     *
     * @param string $path The path of the new file.
     * @param string $fileArray The array of the uploaded file.
     * @param string $name The name of the file.
     * @param string $permission The optional permission level of the new file.
     * @return string
     */
    public function putFileAs($path, $fileArray, $fileName, $permission = '')
    {
        // Handle local driver
        if ($this->filesystemConfig['disks'][$this->disk]['driver'] == 'local') {
            return $this->handleFilePut($path, $fileArray, $fileName, $permission);
        }
    }

    /**
     * Creates a new directory.
     *
     * @param string $path The path of the directory to create.
     * @param string $permission The optional permission level of the new file.
     * @param bool $recursive Indicates whether to recursively create the directory.
     * @return string
     */
    public function makeDirectory($path, $permission = 'public', $recursive = false)
    {
        if (!in_array($permission, ['public', 'private'])) {
            throw new FilesystemException("Invalid permission selection for 'makeDirectory' method (public/private).", 500);
        }

        // Get full folderPath for disk
        trim('/', $path);
        $folderPath = $this->filesystemConfig['disks'][$this->disk]['root'] . '/' . $path;

        // Get permission values
        $permissionValues = $this->permissions[$this->filesystemConfig['disks'][$this->disk]['driver']]['folder'][$permission];

        try {
            mkdir($folderPath, $permissionValues, $recursive);
        } catch (\Exception $e) {
            throw new FilesystemException($e->getMessage(), 500);
        }

        return $folderPath;
    }

    /**
     * Puts a file at a specific location.
     *
     * @param string $path The path of the new file.
     * @param string $fileArray The array of the uploaded file.
     * @param string $name The name of the file.
     * @param string $permission The optional permission level of the new file.
     * @return string
     *
     * @throws \Nebula\Exceptions\FilesystemException
     */
    protected function handleFilePut($path, $fileArray, $fileName = '', $permission = '')
    {
        // Fetch pathinfo for file
        $fileInfo = pathinfo($fileArray['name']);

        // Generate hashed name
        $fileName = $fileName ?? md5($fileArray['tmp_name']) . ".{$fileInfo['extension']}";

        // Get content of file
        $fileContent = file_get_contents($fileArray['tmp_name']);

        // Handle local driver
        if ($this->filesystemConfig['disks'][$this->disk]['driver'] == 'local') {
            trim('/', $path);
            $filepath = $this->filesystemConfig['disks'][$this->disk]['root'] . '/' . $path . '/' . $fileName;

            try {
                file_put_contents(
                    $filepath,
                    $fileContent
                );
            } catch (\Exception $e) {
                throw new FilesystemException($e->getMessage(), 500);
            }

            // Handle permissions
            if (!empty($permission)) {
                if (!in_array($permission, ['public', 'private'])) {
                    throw new FilesystemException("Invalid permission selection for 'put' method.", 500);
                }

                chmod(
                    $filepath,
                    $this->permissions[$this->filesystemConfig['disks'][$this->disk]['driver']]['file'][$permission]
                );
            }
        }

        return $this->url($path . '/' . $fileName) ?? false;
    }
}
