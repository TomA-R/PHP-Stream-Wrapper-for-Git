<?php
/*
 * Copyright (C) 2011 by TEQneers GmbH & Co. KG
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace TQ\Tests\Svn\Repository;

use TQ\Svn\Cli\Binary;
use TQ\Svn\Repository\Repository;
use TQ\Tests\Helper;

class InfoTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        Helper::removeDirectory(TESTS_TMP_PATH);
        Helper::createDirectory(TESTS_TMP_PATH);
        Helper::createDirectory(TESTS_REPO_PATH_1);

        Helper::initEmptySvnRepository(TESTS_REPO_PATH_1);

        for ($i = 0; $i < 5; $i++) {
            $file   = sprintf('file_%d.txt', $i);
            $path   = TESTS_REPO_PATH_1.'/'.$file;
            file_put_contents($path, sprintf('File %d', $i));
            Helper::executeSvn(TESTS_REPO_PATH_1, sprintf('add %s',
                escapeshellarg($file)
            ));
        }
        Helper::executeSvn(TESTS_REPO_PATH_1, sprintf('commit --message=%s',
            escapeshellarg('Initial commit')
        ));

        clearstatcache();
    }

    /**
     * Tears down the fixture, for example, close a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        Helper::removeDirectory(TESTS_TMP_PATH);
    }

    /**
     *
     * @return  Repository
     */
    protected function getRepository()
    {
        return Repository::open(TESTS_REPO_PATH_1, new Binary(SVN_BINARY));
    }

    public function testGetStatus()
    {
        $c  = $this->getRepository();
        $this->assertFalse($c->isDirty());

        $file   = TESTS_REPO_PATH_1.'/test.txt';
        file_put_contents($file, 'Test');
        $this->assertTrue($c->isDirty());
        $status = $c->getStatus();
        $this->assertEquals(array(
            'file'      => 'test.txt',
            'status'    => 'unversioned',
        ), $status[0]);

        $c->add(array('test.txt'));
        $this->assertTrue($c->isDirty());
        $status = $c->getStatus();
        $this->assertEquals(array(
            'file'      => 'test.txt',
            'status'    => 'added',
        ), $status[0]);

        $c->commit('Commt file', array('test.txt'));
        $this->assertFalse($c->isDirty());
    }

    public function testGetLog()
    {
        $c      = $this->getRepository();
        $log    = $c->getLog();
        $this->assertEquals(1, count($log));
        $this->assertContains('Initial commit', $log[0][3]);

        $revision   = $c->writeFile('/directory/test.txt', 'Test');
        $log        = $c->getLog();

        $this->assertEquals(2, count($log));
        $this->assertEquals($revision, $log[0][0]);
        $this->assertContains('Initial commit', $log[1][3]);

        $log    = $c->getLog(1);
        $this->assertEquals(1, count($log));
        $this->assertEquals($revision, $log[0][0]);

        $log    = $c->getLog(1, 1);
        $this->assertEquals(1, count($log));
        $this->assertContains('Initial commit', $log[0][3]);

        $log    = $c->getLog(10,0);
        $this->assertEquals(2, count($log));
        $this->assertContains('Initial commit', $log[1][3]);
    }

    public function testShowCommit()
    {
        $c          = $this->getRepository();
        $revision   = $c->writeFile('test.txt', 'Test');
        $commit = $c->showCommit($revision);
        $this->assertContains('test.txt', $commit);
        $this->assertContains('TQ\Svn\Repository\Repository created or changed file "test.txt"', $commit);
    }

    public function testListDirectory()
    {
        $c      = $this->getRepository();

        $list   = $c->listDirectory();
        $this->assertContains('file_0.txt', $list);
        $this->assertContains('file_1.txt', $list);
        $this->assertContains('file_2.txt', $list);
        $this->assertContains('file_3.txt', $list);
        $this->assertContains('file_4.txt', $list);
        $this->assertNotContains('test.txt', $list);

        $c->writeFile('test.txt', 'Test');
        $list   = $c->listDirectory();
        $this->assertContains('file_0.txt', $list);
        $this->assertContains('file_1.txt', $list);
        $this->assertContains('file_2.txt', $list);
        $this->assertContains('file_3.txt', $list);
        $this->assertContains('file_4.txt', $list);
        $this->assertContains('test.txt', $list);

        $c->removeFile('test.txt');
        $list   = $c->listDirectory();
        $this->assertContains('file_0.txt', $list);
        $this->assertContains('file_1.txt', $list);
        $this->assertContains('file_2.txt', $list);
        $this->assertContains('file_3.txt', $list);
        $this->assertContains('file_4.txt', $list);
        $this->assertNotContains('test.txt', $list);

        $list   = $c->listDirectory('.', '1');
        $this->assertContains('file_0.txt', $list);
        $this->assertContains('file_1.txt', $list);
        $this->assertContains('file_2.txt', $list);
        $this->assertContains('file_3.txt', $list);
        $this->assertContains('file_4.txt', $list);
        $this->assertNotContains('test.txt', $list);

        $list   = $c->listDirectory('.', '2');
        $this->assertContains('file_0.txt', $list);
        $this->assertContains('file_1.txt', $list);
        $this->assertContains('file_2.txt', $list);
        $this->assertContains('file_3.txt', $list);
        $this->assertContains('file_4.txt', $list);
        $this->assertContains('test.txt', $list);

        $c->writeFile('directory/test.txt', 'Test');
        $list   = $c->listDirectory('directory/', 'HEAD');
        $this->assertContains('test.txt', $list);

        $list   = $c->listDirectory('directory', 'HEAD');
        $this->assertContains('test.txt', $list);
    }
}
