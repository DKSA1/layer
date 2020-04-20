<?php


namespace layer\core\utils;


class File
{
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
    private $name;
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
            $this->path = $path;
            $this->absolutePath = realpath($path);
            $this->name = basename($path);
            $this->type = filetype($path);
            $this->size = filesize($path);
            $this->readable = is_readable($path);
            $this->executable = is_executable($path);
            $this->uploadFile = is_uploaded_file($path);
            $this->writeable = is_writeable($path);
            $this->creationTime = filectime($path);
            $this->lastModificationTime = filemtime($path);
            $this->lastAccessTime = fileatime($path);
            $this->extension = pathinfo($this->absolutePath, PATHINFO_EXTENSION);
    }

    public function touch() {
        if($this->deleted) return null;
        return touch($this->absolutePath);
    }

    public function copy($path) {
        if($this->deleted) return null;
    }

    public function rename($newName) {
        if($this->deleted) return null;
        $res = rename($this->absolutePath, dirname($this->absolutePath).'/'.$newName);
        if($res) {
            $this->name = $newName;
            $this->absolutePath = dirname($this->absolutePath).'/'.$newName;
            $this->path = dirname($this->absolutePath).'/'.$newName;
        }
        return $res;
    }

    public function move($newPath) {
        if($this->deleted) return null;
    }

    public function delete() {
        if($this->deleted) return null;
        $this->deleted = unlink($this->absolutePath);
        return $this->deleted;
    }

    public function serve() {
        if($this->deleted) return null;
        // send specific headers for file download
        // build temp folder with temp file
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
    public function getName()
    {
        return $this->name;
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

}