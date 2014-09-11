<?php
class MassiTest extends PHPUnit_Framework_TestCase {
   public function testBasicClass()
    {
        $c = new stdClass();
        $this->assertInstanceOf('stdClass', $c);
    }
}