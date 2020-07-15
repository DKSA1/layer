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
            $this->loaded = false;
    }

    public function load() {
        if($this->isDeleted() || $this->loaded) return false;
        if($data = file_get_contents($this->getAbsolutePath())) {
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
        if(!file_exists(dirname($newPath))) {
            if(!mkdir(dirname($newPath),0777, true))
                return false;
        }
        move_uploaded_file($this->absolutePath, $newPath);
    }

    public function delete() {
        if($this->deleted) return null;
        $this->deleted = unlink($this->absolutePath);
        return $this->deleted;
    }

    // TODO: send specific headers and point to ressource
    public function serve() {
        if($this->deleted) return null;
        // send specific headers for file download
        // build temp folder with temp file
    }

    // TODO : zip if folder or file
    public function zip() {

    }

    // TODO : unzip if zip
    public function unzip() {

    }

    /**
     * @return false|string
     */
    public function getAbsolutePath()
    {
        if(!$this->absolutePath) {
            $this->absolutePath = realpath($this->path);
        }
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
        if(!$this->name) {
            $this->name = basename($this->path);
        }
        return $this->name;
    }

    /**
     * @return false|string
     */
    public function getType()
    {
        if(!$this->type) {
            $this->type = filetype($this->path);
        }
        return $this->type;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        if($this->extension === null) {
            $this->extension = pathinfo($this->absolutePath, PATHINFO_EXTENSION);
        }
        return $this->extension;
    }

    /**
     * @return false|int
     */
    public function getSize()
    {
        if(!$this->size) {
            $this->size = filesize($this->path);
        }
        return $this->size;
    }

    /**
     * @return bool
     */
    public function isReadable()
    {
        if($this->readable === null) {
            $this->readable = is_readable($this->path);
        }
        return $this->readable;
    }

    /**
     * @return bool
     */
    public function isExecutable(): bool
    {
        if($this->executable === null) {
            $this->executable = is_executable($this->path);
        }
        return $this->executable;
    }

    /**
     * @return bool
     */
    public function isUploadFile()
    {
        if($this->uploadFile === null) {
            $this->uploadFile = is_uploaded_file($this->path);
        }
        return $this->uploadFile;
    }

    /**
     * @return bool
     */
    public function isWriteable()
    {
        if($this->writeable === null) {
            $this->writeable = is_writeable($this->path);
        }
        return $this->writeable;
    }

    /**
     * @return false|int
     */
    public function getCreationTime()
    {
        if($this->creationTime === null) {
            $this->creationTime = filectime($this->path);
        }
        return $this->creationTime;
    }

    /**
     * @return false|int
     */
    public function getLastModificationTime()
    {
        if($this->lastModificationTime === null) {
            $this->lastModificationTime = filemtime($this->path);
        }
        return $this->lastModificationTime;
    }

    /**
     * @return false|int
     */
    public function getLastAccessTime()
    {
        if($this->lastAccessTime === null) {
            $this->lastAccessTime = fileatime($this->path);
        }
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

}