<?php

/**
 * This decorated result adds the functionality to check for user input. We
 * need a distinction between a single digit read (this class) and a data read
 * (DataReadResult) because asterisk sends the ascii number for the character
 * read (the first case) and the literal string in the latter.
 *
 * PHP Version 5
 *
 * @category   Pagi
 * @package    Client
 * @subpackage Result
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @license    http://marcelog.github.com/PAGI/ Apache License 2.0
 * @version    SVN: $Id$
 * @link       http://marcelog.github.com/PAGI/
 *
 * Copyright 2011 Marcelo Gornstein <marcelog@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace PAGI\Client\Result;

use PAGI\Client\Impl\ClientImpl as PagiClient;
use PAGI\Exception\ChannelDownException;
use PAGI\Exception\SoundFileException;

/**
 * This decorated result adds the functionality to check for user input. We
 * need a distinction between a single digit read (this class) and a data read
 * (DataReadResult) because asterisk sends the ascii number for the character
 * read (the first case) and the literal string in the latter.
 *
 * PHP Version 5
 *
 * @category   Pagi
 * @package    Client
 * @subpackage Result
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @license    http://marcelog.github.com/PAGI/ Apache License 2.0
 * @link       http://marcelog.github.com/PAGI/
 */
class ControlStreamResult extends ResultDecorator implements IReadResult {

    /**
     * Digits read (if any).
     * @var string
     */
    protected $digits;

    /**
     * Timeout?
     * @var boolean
     */
    protected $timeout;

    /**
     * Offset?
     * @var int
     */
    private $offset;

    /**
     * (non-PHPdoc)
     * @see PAGI\Client\Result.IReadResult::isTimeout()
     */
    public function isTimeout() {
        return $this->timeout;
    }

    /**
     * (non-PHPdoc)
     * @see PAGI\Client\Result.IReadResult::getDigits()
     */
    public function getDigits() {
        return $this->digits;
    }

    /**
     * (non-PHPdoc)
     * @see PAGI\Client\Result.IReadResult::getDigitsCount()
     */
    public function getDigitsCount() {
        return strlen($this->digits);
    }

    /**
     * status?
     * @var integer
     */
    protected $status;

    const SUCCESS = 0;
    const USERSTOPPED = 1;
    const REMOTESTOPPED = 2;
    const ERROR = -1;

    /**
     * Constructor.
     *
     * @param IResult $result Result to decorate.
     *
     * @return void
     */
    public function __construct(IResult $result) {
        parent::__construct($result);
        $this->digits = false;
        $this->timeout = false;
        $result = $result->getResult();
        switch ($result) {
            case -1:
                $pagiClient = PagiClient::getInstance(array());
                $var = $pagiClient->getFullVariable('CPLAYBACKSTATUS');
                $pagiClient->consoleLog($var . '-' . $pagiClient->getFullVariable('CPLAYBACKOFFSET'));
                switch ($var) {
                    case 'SUCCESS':
                        $this->timeout = true;
                        $this->status = self::SUCCESS;
                        break;
                    case 'USERSTOPPED':
                        $this->timeout = FALSE;
                        $this->status = self::USERSTOPPED;
                        break;
                    case 'REMOTESTOPPED':
                        $this->timeout = FALSE;
                        $this->status = self::REMOTESTOPPED;
                        break;
                    case 'ERROR':
                        $this->timeout = FALSE;
                        $this->status = self::ERROR;
                        if ($pagiClient->getFullVariable('CPLAYBACKOFFSET') > 0) {
                            //throw new ChannelDownException();
                            $this->timeout = true;
                            $this->status = self::SUCCESS;
                        } else {
                            //throw new SoundFileException();
                            $this->timeout = true;
                            $this->status = self::SUCCESS;
                        }
                        break;
                    default:
                        break;
                }

                break;
            case 0:
                $this->timeout = true;
                break;
            default:
                $this->digits = chr(intval($result));
                break;
        }
    }

    public function getPlaybackOffset(): integer {
        return $this->offset;
    }

    public function getPlaybackStatus(): int {
        return $this->status;
    }

    public function getPlaybackStopKey(): string {
        return $this->digits;
    }

}
