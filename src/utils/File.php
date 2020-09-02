<?php

namespace rloris\layer\utils;

class File
{
    /**
     * @var bool
     */
    private $loaded;
    /**
     * @var string
     */
    private $content;
    /**
     * @var string
     */
    private $uploadName;
    /**
     * @var string
     */
    private $mimeType;
    /**
     * @var false|string
     */
    private $absolutePath;
    /**
     * @var string
     */
    private $path;
    /**
     * @var string
     */
    private $fullname;
    /**
     * @var string
     */
    private $basename;
    /**
     * @var false|string
     */
    private $type;
    /**
     * @var string
     */
    private $extension;
    /**
     * @var false|int
     */
    private $size;
    /**
     * @var bool
     */
    private $readable;
    /**
     * @var bool
     */
    private $executable;
    /**
     * @var bool
     */
    private $uploadFile;
    /**
     * @var bool
     */
    private $writeable;
    /**
     * @var false|int
     */
    private $creationTime;
    /**
     * @var false|int
     */
    private $lastModificationTime;
    /**
     * @var false|int
     */
    private $lastAccessTime;
    /**
     * @var bool
     */
    private $deleted = false;
    /**
     * @var string $downloadName
     */
    private $downloadName = null;

    public static function getInstance(string $path, string $uploadName = null, string $mimeType = null) {
        if(file_exists($path)) {
            return new File($path, $uploadName, $mimeType);
        }
        return null;
    }

    private function __construct(string $path, string $uploadName = null, string $mimeType = null)
    {
            $this->uploadName = $uploadName;
            $this->mimeType = $mimeType;
            $this->loaded = false;
            $this->updateMetadata($path);
    }

    private function updateMetadata($newPath)
    {
        $this->path = $newPath;
        $this->fullname = basename($this->path);
        $this->absolutePath = realpath($this->path);
        $this->absolutePath = realpath($this->path);
        $this->fullname = basename($this->path);
        $this->basename = pathinfo($this->path, PATHINFO_FILENAME);
        $this->writeable = is_writeable($this->path);
        $this->uploadFile = is_uploaded_file($this->path);
        $this->executable = is_executable($this->path);
        $this->readable = is_readable($this->path);
        $this->size = filesize($this->path);
        $this->type = filetype($this->path);
        $this->extension = pathinfo($this->absolutePath, PATHINFO_EXTENSION);
        $this->lastAccessTime = fileatime($this->path);
        $this->lastModificationTime = filemtime($this->path);
        $this->creationTime = filectime($this->path);
    }

    public function load() {
        if($this->isDeleted() || $this->loaded) return false;
        if($data = file_get_contents($this->getAbsolutePath()))
        {
            $this->content = $data;
            $this->loaded = true;
            return true;
        }
        return false;
    }

    public function touch() {
        if($this->deleted) return null;
        return touch($this->absolutePath);
    }

    public function copy(string $path, bool $overwrite = true): bool {
        if($this->deleted) return null;
        if(file_exists($path) && $overwrite === false) return false;
        return copy($this->absolutePath, $path);
    }

    public function rename($newName) {
        if($this->deleted) return null;
        $res = rename($this->absolutePath, dirname($this->absolutePath).'/'.basename($newName));
        if($res) {
            $this->fullname = basename($newName);
            $this->absolutePath = dirname($this->absolutePath).'/'.$this->fullname;
            $this->path = dirname($this->absolutePath).'/'.$this->fullname;
        }
        return $res;
    }

    public function move($newPath) {
        if($this->deleted) return null;
        if(!file_exists(dirname($newPath))) {
            if(!mkdir(dirname($newPath),0777, true))
                return false;
        }
        if(move_uploaded_file($this->absolutePath, $newPath))
        {
            $this->updateMetadata($newPath);
        }
    }

    public function delete() {
        if($this->deleted) return null;
        $this->deleted = unlink($this->absolutePath);
        return $this->deleted;
    }

    /**
     * @param string|NULL $dest
     * @param bool $overwrite
     * @return bool
     */
    public function zip(string $dest = NULL, bool $overwrite = true): bool
    {
        $dest = ($dest !== null ? $dest : $this->fullname.".zip");

        if($overwrite === false && file_exists($dest)) return false;

        $zip = new \ZipArchive();
        if($zip->open($dest, (($overwrite === true && file_exists($dest)) ? \ZipArchive::OVERWRITE : \ZipArchive::CREATE)) === true)
        {
            $res = $zip->addFile($this->absolutePath, $this->fullname);
            $zip->close();
            return $res;
        }

        return false;
    }

    /**
     * @param string|NULL $dest
     * @param bool $overwrite
     * @return bool
     */
    public function unzip(string $dest = NULL, bool $overwrite = true): bool
    {
        $dest = ($dest !== null ? $dest : './'.$this->basename);

        if($overwrite === false && file_exists($dest)) return false;

        $zip = new \ZipArchive();
        if($zip->open($this->absolutePath) === TRUE)
        {
            $res = $zip->extractTo($dest);
            $zip->close();
            return $res;
        }

        return false;
    }

    /**
     * @return false|string
     */
    public function getAbsolutePath()
    {
        return $this->absolutePath;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getFullname()
    {
        return $this->fullname;
    }

    /**
     * @return false|string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @return false|int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @return bool
     */
    public function isReadable()
    {
        return $this->readable;
    }

    /**
     * @return bool
     */
    public function isExecutable(): bool
    {
        return $this->executable;
    }

    /**
     * @return bool
     */
    public function isUploadFile()
    {
        return $this->uploadFile;
    }

    /**
     * @return bool
     */
    public function isWriteable()
    {
        return $this->writeable;
    }

    /**
     * @return false|int
     */
    public function getCreationTime()
    {
        return $this->creationTime;
    }

    /**
     * @return false|int
     */
    public function getLastModificationTime()
    {
        return $this->lastModificationTime;
    }

    /**
     * @return false|int
     */
    public function getLastAccessTime()
    {
        return $this->lastAccessTime;
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * @return string
     */
    public function getUploadName()
    {
        return $this->uploadName;
    }

    /**
     * @return string
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * @return bool
     */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return string
     */
    public function getBasename(): string
    {
        return $this->basename;
    }

    /**
     * @return string
     */
    public function getDownloadName()
    {
        return $this->downloadName;
    }

    /**
     * @param string $downloadName
     */
    public function setDownloadName(string $downloadName)
    {
        $this->downloadName = $downloadName;
    }

}