<?php namespace AwkwardIdeas\ModelMaker\Models;

class Table extends BaseObject{

    protected $name;
    protected $comments;
    protected $storage_engine;
    protected $collation;
    protected $auto_increment;
    protected $row_format;
    protected $columns;
    protected $indexes;
}