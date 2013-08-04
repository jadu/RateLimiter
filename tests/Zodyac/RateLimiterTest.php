<?php

namespace Zodyac;

use Zodyac\Cache\Result;

class RateLimiterTest extends \PHPUnit_Framework_TestCase
{
    public $cache;
    public $rateLimiter;
    public $identifiers;
    public $time;

    public function setUp()
    {
        $this->cache = $this->getMock('Zodyac\Cache\Storage\StorageInterface');
        $this->rateLimiter = new RateLimiter($this->cache, 10, 3);

        // Parmaters to identify a user
        $this->identifiers = array('ip' => '127.0.0.1', 'username' => 'test');

        // Current timestamp (hard-coded to ease testing)
        $this->time = 1361979524;
    }

    public function testExceededReturnsFalseWhenTheCounterCachesAreEmpty()
    {
        $this->assertFalse($this->rateLimiter->exceeded($this->identifiers, $this->time));
    }

    public function testExceededReturnsFalseWhenTheCounterSumDoesNotExceedTheLimit()
    {
        // Return a small value so we don't exceed the limit (3x1=3) < 10
        $this->cache->expects($this->any())->method('getMulti')
            ->will($this->returnValue(array(
                new Result('key', true, 1),
                new Result('key', true, 1),
                new Result('key', true, 1)
            )));

        $this->assertFalse($this->rateLimiter->exceeded($this->identifiers, $this->time));
    }

    public function testExceededReturnsTrueWhenTheCounterSumExceedsTheLimit()
    {
        // Always return a big value to exceed the limit (3x100=300) > 10
        $this->cache->expects($this->any())->method('getMulti')
            ->will($this->returnValue(array(
                new Result('key', true, 100),
                new Result('key', true, 100),
                new Result('key', true, 100)
            )));

        $this->assertTrue($this->rateLimiter->exceeded($this->identifiers, $this->time));
    }

    public function testTotalCountValueIsCalculatedFromThreeCountCachesWhenThePeriodIsThree()
    {
        $this->cache->expects($this->once())->method('getMulti')
            ->with($this->equalTo(array(
                'RateLimit:201302271538:e04ade7fbce4c5af1d6838b92aa66f1a51a12de2',
                'RateLimit:201302271537:e04ade7fbce4c5af1d6838b92aa66f1a51a12de2',
                'RateLimit:201302271536:e04ade7fbce4c5af1d6838b92aa66f1a51a12de2'
            )));

        $this->rateLimiter->exceeded($this->identifiers, $this->time);
    }

    public function testTotalCountValueIsCalculatedFromFiveCountCachesWhenThePeriodIsFive()
    {
        $this->cache->expects($this->once())->method('getMulti')
            ->with($this->equalTo(array(
                'RateLimit:201302271538:e04ade7fbce4c5af1d6838b92aa66f1a51a12de2',
                'RateLimit:201302271537:e04ade7fbce4c5af1d6838b92aa66f1a51a12de2',
                'RateLimit:201302271536:e04ade7fbce4c5af1d6838b92aa66f1a51a12de2',
                'RateLimit:201302271535:e04ade7fbce4c5af1d6838b92aa66f1a51a12de2',
                'RateLimit:201302271534:e04ade7fbce4c5af1d6838b92aa66f1a51a12de2'
            )));

        $rateLimiter = new RateLimiter($this->cache, 10, 5);
        $rateLimiter->exceeded($this->identifiers, $this->time);
    }

    public function testIncrementUpdatesTheCurrentCounterValue()
    {
        $this->cache->expects($this->once())->method('increment')
            ->with($this->equalTo('RateLimit:201302271538:e04ade7fbce4c5af1d6838b92aa66f1a51a12de2'));

        $this->rateLimiter->increment($this->identifiers, $this->time);
    }
}
