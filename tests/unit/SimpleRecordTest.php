<?php


require 'stub/Post.php';
require 'stub/Comment.php';

use SimpleRecord\Record;
use Codeception\Util\Stub;

class SimpleRecordTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    protected $db;
    /**
     * @var Record
     */
    protected $record;

    protected function _before()
    {
        $config = [
            'driver' => 'pdo_sqlite',
            'dbname' => 'sqlite:///:memory:',
        ];

        $this->db = \Doctrine\DBAL\DriverManager::getConnection($config);

        $schema = file_get_contents(codecept_data_dir() . '/dump.sql');
        $this->db->exec($schema);

//        $this->db->insert('table_comment',['username'=>'tester','post_id'=>1]);
//        $this->db->query('select * from table_comment')->fetchAll();

        $this->record = new Record();
        $this->record->connection($this->db);
    }

    protected function _after()
    {
    }

    public function testConnection()
    {
        $record = new Record();
        $record->connection($this->db);

        $this->assertSame($this->db, $record->getConnection());
    }

    public function testTableNameByClassName()
    {
        $table_name = $this->record->tableName('Post');
        $this->assertSame($table_name, 'post');
    }

    public function testTableNameByConst()
    {
        $table_name = $this->record->tableName('Comment');
        $this->assertSame($table_name, 'table_comment');
    }

    public function testTableNameByThis()
    {
        $post = new Post();
        $table_name = $post->tableName();
        $this->assertSame($table_name, 'post');
    }

    public function testConstructByData()
    {
        $data = [
            'id' => 1
        ];
        $record = new Record($data);
        $this->assertSame(1, $record->id);
    }

    public function testConstructByPdoFetch()
    {
        $stub_record = Stub::make('SimpleRecord\Record', ['afterFetch' => Stub::once(function () {
            return true;
        })]);
        $stub_record->__construct(null, true);
    }

    public function testSaveBeforeSave()
    {
        $stub_record = Stub::make('Post', ['beforeSave' => Stub::once(function () {
            return false;
        })]);
        $result = $stub_record->save();

        $this->assertFalse($result);
    }

    public function testBeforeInsert()
    {
        $stub_record = Stub::make('Comment', ['TABLE_NAME' => '123', 'beforeInsert' => Stub::once(function () {
            return false;
        })]);
        $stub_record->slug = 'testuser';
        $stub_record->title = 1;
        $result = $stub_record->save();

        $this->assertFalse($result);
    }

    public function testSaveInsert()
    {
        $stub_record = Stub::makeEmptyExcept('Comment', 'save',
            [
                'tableName' => function () {
                    return 'comment';
                },
                'beforeSave' => Stub::once(function () {
                    return true;
                }),
                'beforeInsert' => Stub::once(function () {
                    return true;
                }),
                'afterInsert' => Stub::once(function () {
                    return true;
                }),
                'afterSave' => Stub::once(function () {
                    return true;
                }),
                'getColumns' => function () {
                    return ['id', 'username', 'post_id'];
                }
            ]);

        $stub_record->username = 'testuser';
        $stub_record->post_id = 1;
        $result = $stub_record->save();

        $this->assertSame('testuser', $stub_record->username);
        $this->assertTrue(is_numeric($stub_record->id));
        $this->assertTrue($result);

    }

//    public function testUnit()
//    {
//        $stub_record = $this->getMockBuilder('SimpleRecord\Record')->disableOriginalConstructor()
//                            ->getMock();
////        $stub_record = $this->getMockClass('Record');
////        var_dump($stub_record);
////        exit;
//        $stub_record->expects($this->once())
//                    ->method('beforeSave')
//                    ->with(100)
//                    ->willReturn(100);
//    }

    public function testSaveUpdate()
    {

        $this->testSaveInsert();
        $stub_record = Stub::makeEmptyExcept('Comment', 'save',
            [
                'tableName' => function () {
                    return 'comment';
                },
                'beforeSave' => Stub::once(function () {
                    return true;
                }),
                'beforeInsert' => Stub::never(function () {
                    return true;
                }),
                'afterInsert' => Stub::never(function () {
                    return true;
                }),
                'afterSave' => Stub::once(function () {
                    return true;
                }),
                'beforeUpdate' => Stub::once(function () {
                    return true;
                }),
                'afterUpdate' => Stub::once(function () {
                    return true;
                }),
                'getColumns' => function () {
                    return ['id', 'username', 'post_id'];
                }
            ]);


        $stub_record->id = 1;
        $stub_record->username = 'testuser2';
        $stub_record->post_id = 1;
        $result = $stub_record->save();

        $this->assertSame('testuser2', $stub_record->username);
        $this->assertTrue(is_numeric($stub_record->id));
        $this->assertTrue($result);

    }


    public function testDelete()
    {
        $post = new Post();
        $post->slug = 'slug';
        $post->title = 'title';
        $post->save();
        $result = $post->delete();

        $this->assertTrue($result);
    }

    /**
     * @expectedException        \Exception
     * @expectedExceptionMessage cant delete without id
     */
    public function testDeleteFail()
    {
        $post = new Post();
        $post->delete();
    }

    public function testFindByIdFail()
    {
        $post = new Post();
        $this->assertFalse($post->find(101));
    }

    public function testFindAll()
    {
        $stub_record = Stub::makeEmptyExcept('Comment', 'findAll',
            [
                'findBy' => Stub::once(function () {
                    return true;
                })
            ]);
        $stub_record->findAll();
    }

    public function testFindBy()
    {
        $post = new Post(['slug' => 'slug1', 'title' => 'title1']);
        $post->save();
        $post = new Post(['slug' => 'slug2', 'title' => 'title2']);
        $post->save();
        $post = new Post(['slug' => 'slug3', 'title' => 'title3']);
        $post->save();
        $post = new Post(['slug' => 'slug4', 'title' => 'title4']);
        $post->save();
        $post = new Post(['slug' => 'slug5', 'title' => 'title5']);
        $post->save();


        $collection = $post->findBy(['id' => [1, 2, 3, 4]], ['id' => 'DESC'], 3, 1);
        $this->assertCount(3, $collection);
        $this->assertEquals(3, $collection[0]->id);
    }

    /**
     * @expectedException        \Exception
     * @expectedExceptionMessage finded more than one
     */
    public function testFindOneByFail()
    {
        $post = new Post(['slug' => 'slug1', 'title' => 'title1']);
        $post->save();
        $post = new Post(['slug' => 'slug2', 'title' => 'title2']);
        $post->save();

        $post->findOneBy(['id' => [1, 2]]);
    }

    public function testFindOneBy()
    {
        $post = new Post(['slug' => 'slug1', 'title' => 'title1']);
        $post->save();
        $post = new Post(['slug' => 'slug2', 'title' => 'title2']);
        $post->save();
        $item = $post->findOneBy(['id' => 2]);

        $this->assertEquals($post, $item);
    }
}