<?php

class Comment extends SimpleRecord\Record
{
    public $id;
    public $username;
    public $post_id;

    const TABLE_NAME = 'table_comment';

//    public function getColumns()
//    {
//        return ['id', 'username', 'post_id'];
//    }
}