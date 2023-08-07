<?php
/**
 * Interface for a control stream result, so it can be decorated later.
 *
 * PHP Version 5
 *
 * @category   Pagi
 * @package    Client
 * @subpackage Result
 * @author     moti irom <moti09@gmail.com>
 *
 * Copyright 2011 Marcelo Gornstein <marcelog@gmail.com>
 *
 */
namespace PAGI\Client\Result;

interface IControlStreamResult extends IResult
{
    /**
     * Returns the playback status.
     * SUCCESS, USERSTOPPED, REMOTESTOPPED, ERROR.
     *
     * @return int
     */
    public function getPlaybackStatus();

    /**
     * If the playback is stopped by the user this variable contains the key that was pressed.
     *
     * @return string
     */
    public function getPlaybackStopKey();
    /**
     * Return the offset in ms into the file where playback was at when it stopped. -1 is end of file.
     * 
     * @return integer
     */
    public function getPlaybackOffset();
}