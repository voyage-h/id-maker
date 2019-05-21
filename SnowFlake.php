<?php

/**
 * SnowFlake ID Generator
 * Based on Twitter Snowflake to generate unique ID across multiple
 * datacenters and databases without having duplicates.
 *
 *
 * SnowFlake Layout
 *
 * 1 sign bit -- 0 is positive, 1 is negative
 * 41 bits -- milliseconds since epoch
 * 5 bits -- dataCenter ID
 * 5 bits -- machine ID
 * 12 bits -- sequence number
 *
 * Total 64 bit integer/string
 */

class SnowFlake
{
    private static $instance = null;

    public static function __callStatic($method, $args)
    {
        if (!self::$instance || 
            !self::$instance instanceof self) {
            self::$instance = new self();
        }
        return call_user_func_array([self::$instance, $method], $args);
    }
    /**
     * Offset from Unix Epoch
     * Unix Epoch : January 1 1970 00:00:00 GMT
     * Epoch Offset : January 1 2000 00:00:00 GMT
     */
    const EPOCH_OFFSET = 1483200000000;
    const SIGN_BITS = 1;
    const TIMESTAMP_BITS = 41;
    const DATACENTER_BITS = 5;
    const MACHINE_ID_BITS = 5;
    const SEQUENCE_BITS = 12;

    /**
     * @var mixed
     */
    protected $datacenter_id;

    /**
     * @var mixed
     */
    protected $machine_id;

    /**
     * @var null|int
     */
    protected $lastTimestamp = null;

    /**
     * @var int
     */
    protected $sequence = 1;
    protected $signLeftShift = 
        self::TIMESTAMP_BITS + 
        self::DATACENTER_BITS + 
        self::MACHINE_ID_BITS + 
        self::SEQUENCE_BITS;

    protected $timestampLeftShift = 
        self::DATACENTER_BITS + 
        self::MACHINE_ID_BITS + 
        self::SEQUENCE_BITS;

    protected $dataCenterLeftShift = 
        self::MACHINE_ID_BITS + 
        self::SEQUENCE_BITS;

    protected $machineLeftShift = self::SEQUENCE_BITS;

    //位运算计算n位能表示的最大整数
    //-1左移12位和-1异或得到4095
    //补码=反码+1 或 补码=(原码-1)去反码
    protected $maxSequenceId = -1 ^ (-1 << self::SEQUENCE_BITS);//2**self::SEQUENCE_BITS-1
    protected $maxMachineId = -1 ^ (-1 << self::MACHINE_ID_BITS);//2**self::MACHINE_ID_BITS-1
    protected $maxDataCenterId = -1 ^ (-1 << self::DATACENTER_BITS);

    /**
     * Constructor to set required paremeters
     *
     * @param mixed $dataCenter_id Unique ID for datacenter (if multiple locations are used)
     * @param mixed $machine_id Unique ID for machine (if multiple machines are used)
     * @throws \Exception
     */
    private function __construct($dataCenter_id = 1, $machine_id = 1)
    {
        if ($dataCenter_id > $this->maxDataCenterId) {
            throw new \Exception('dataCenter id should between 0 and ' . $this->maxDataCenterId);
        }
        if ($machine_id > $this->maxMachineId) {
            throw new \Exception('machine id should between 0 and ' . $this->maxMachineId);
        }
        $this->datacenter_id = $dataCenter_id;
        $this->machine_id = $machine_id;
    }

    /**
     * Generate an unique ID based on SnowFlake
     * @return string
     * @throws \Exception
     */
    private function next()
    {
        $sign = 0; // default 0
        $timestamp = $this->getUnixTimestamp();
        if ($timestamp < $this->lastTimestamp) {
            throw new \Exception('"Clock moved backwards!');
        }
        if ($timestamp == $this->lastTimestamp) { //与上次时间戳相等，需要生成序列号
            $sequence = ++$this->sequence;
            if ($sequence == $this->maxSequenceId) { //如果序列号超限，则需要重新获取时间
                $timestamp = $this->getUnixTimestamp();
                while ($timestamp <= $this->lastTimestamp) {
                    $timestamp = $this->getUnixTimestamp();
                }
                $this->sequence = 0;
                $sequence = ++$this->sequence;
            }
        } else {
            $this->sequence = 0;
            $sequence = ++$this->sequence;
        }
        $this->lastTimestamp = $timestamp;
        $time = (int)($timestamp - self::EPOCH_OFFSET);
        $id = ($sign << $this->signLeftShift) | 
            ($time << $this->timestampLeftShift) | 
            ($this->datacenter_id << $this->dataCenterLeftShift) | 
            ($this->machine_id << $this->machineLeftShift) | 
            $sequence;

        return $id;
    }

    /**
     * Get UNIX timestamp in microseconds
     *
     * @return int  Timestamp in microseconds
     */
    private function getUnixTimestamp()
    {
        return floor(microtime(true) * 1000);
    }
}

