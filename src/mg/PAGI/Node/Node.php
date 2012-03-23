<?php
/**
 * A node, used to get input from the user, validate it, play prompt messages,
 * etc.
 *
 * PHP Version 5.3
 *
 * @category PAGI
 * @package  Node
 * @author   Marcelo Gornstein <marcelog@gmail.com>
 * @license  http://marcelog.github.com/PAGI/ Apache License 2.0
 * @version  SVN: $Id$
 * @link     http://marcelog.github.com/PAGI/
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
namespace PAGI\Node;

use PAGI\Client\IClient;
use PAGI\Node\Exception\NodeException;

/**
 * A node, used to get input from the user, validate it, play prompt messages,
 * etc.
 *
 * @category PAGI
 * @package  Node
 * @author   Marcelo Gornstein <marcelog@gmail.com>
 * @license  http://marcelog.github.com/PAGI/ Apache License 2.0
 * @link     http://marcelog.github.com/PAGI/
 */
class Node
{
    /**
     * Any of the available DTMF digits in a classic telephone.
     * @var string
     */
    const DTMF_ANY = '0123456789*#';
    /**
     * DTMF digit '*'
     * @var string
     */
    const DTMF_STAR = '*';
    /**
     * DTMF digit '#'
     * @var string
     */
    const DTMF_HASH = '#';
    /**
     * DTMF digits which are integers (numbers)
     * @var string
     */
    const DTMF_NUMERIC = '1234567890';
    /**
     * DTMF digits non numeric
     * @var string
     */
    const DTMF_NONNUMERIC = '#*';
    /**
     * DTMF digit '1'
     * @var string
     */
    const DTMF_1 = '1';
    /**
     * DTMF digit '2'
     * @var string
     */
    const DTMF_2 = '2';
    /**
     * DTMF digit '3'
     * @var string
     */
    const DTMF_3 = '3';
    /**
     * DTMF digit '4'
     * @var string
     */
    const DTMF_4 = '4';
    /**
     * DTMF digit '5'
     * @var string
     */
    const DTMF_5 = '5';
    /**
     * DTMF digit '6'
     * @var string
     */
    const DTMF_6 = '6';
    /**
     * DTMF digit '7'
     * @var string
     */
    const DTMF_7 = '7';
    /**
     * DTMF digit '8'
     * @var string
     */
    const DTMF_8 = '8';
    /**
     * DTMF digit '9'
     * @var string
     */
    const DTMF_9 = '9';
    /**
     * DTMF digit '0'
     * @var string
     */
    const DTMF_0 = '0';

    /**
     * State when the node has not be run yet.
     * @var integer
     */
    const STATE_NOT_RUN = 0;
    /**
     * State reached when the node can be cancelled (it has a cancel digit set)
     * and the user pressed it.
     * @var integer
     */
    const STATE_CANCEL = 1;
    /**
     * State reached when the input is considered complete (it has at least
     * the minimum number of digits)
     * @var integer
     */
    const STATE_COMPLETE = 2;
    /**
     * The user has not entered any input.
     * @var integer
     */
    const STATE_TIMEOUT = 3;
    /**
     * The user exceeded the maximum number of attempts allowed to enter
     * a valid option.
     * @var integer
     */
    const STATE_MAX_INPUTS_REACHED = 4;

    /**
     * Used when evaluating input, returned when the user presses the end of
     * input digit.
     * @var integer
     */
    const INPUT_END = 0;
    /**
     * Used when evaluating input, returned when the user presses the cancel
     * digit.
     * @var integer
     */
    const INPUT_CANCEL = 1;
    /**
     * Used when evaluating input, returned when the user presses a digit
     * that is not a cancel or end of input digit.
     * @var integer
     */
    const INPUT_NORMAL = 2;

    /**
     * Holds the PAGI client.
     * @var PAGI\Client\IClient
     */
    private $_client = null;
    /**
     * Here's where the user input is appended one digit at the time.
     * @var string
     */
    private $_input = '';
    /**
     * Node state.
     * @var integer
     */
    private $_state = self::STATE_NOT_RUN;
    /**
     * Holds the configured end of input digit.
     * @var string
     */
    private $_endOfInputDigit = 'X';
    /**
     * Holds the configured cancel digit.
     * @var string
     */
    private $_cancelDigit = 'X';
    /**
     * The minimum configured expected input length.
     * @var integer
     */
    private $_minInput = 0;
    /**
     * The maximum configured expected input length.
     * @var integer
     */
    private $_maxInput = 0;
    /**
     * In milliseconds, maximum time to wait for user input between digits.
     * Only taken into account when expecting input outside prompt and preprompt
     * messages.
     * @var integer
     */
    private $_timeBetweenDigits = '-1';
    /**
     * In milliseconds, maximum time to wait for a complete user input (per
     * attempt).
     * @var integer
     */
    private $_totalTimeForInput = '-1';
    /**
     * Holds the prompt messages (actions) to be used before expecting the user
     * input (like sounds, numbers, datetimes, etc).
     * @var string[]
     */
    private $_promptMessages = array();
    /**
     * Similar to prompt messages, but dynamically populated and cleared with
     * pre prompt messages, like error messages from validations.
     * @var string[]
     */
    private $_prePromptMessages = array();
    /**
     * True if the pre prompt messages can be interrupted with a dtmf digit.
     * @var boolean
     */
    private $_prePromptMessagesInterruptable = true;
    /**
     * When the user can interrupt the pre prompt messages, this will indicate
     * if the digit pressed count as input (thus, discarding the prompt
     * messages).
     * @var boolean
     */
    private $_acceptPrePromptInputAsInput = true;
    /**
     * Node name.
     * @var string
     */
    private $_name = 'X';
    /**
     * Holds all input validators.
     * @var Closure[]
     */
    private $_inputValidations = array();
    /**
     * Total attempts for the user to enter a valid input. Will loop input
     * routine this many times when the input is not validated.
     * @var integer
     */
    private $_totalAttemptsForInput = 1;
    /**
     * Optional message to play when the user did not enter any digits on
     * input.
     * @var string
     */
    private $_onNoInputMessage = null;
    /**
     * Optinal message to play when the user exceeded the maximum allowed
     * attempts to enter a valid input.
     * @var message
     */
    private $_onMaxValidInputAttempts = null;
    /**
     * True if prompt messages can be interrupted.
     * @var boolean
     */
    private $_promptsCanBeInterrupted = true;
    /**
     * When pre prompt or prompt messages can be interrupted, these are the
     * valid interrupt digits.
     * @var string
     */
    private $_validInterruptDigits = self::DTMF_ANY;
    /**
     * Carries state. This is where optional custom data can be saved in the
     * callbacks and 3rd party software. Keys are strings.
     * @var mixed[]
     */
    private $_registry = array();
    /**
     * Callback to execute on valid input from the user.
     * @var Closure
     */
    private $_executeOnValidInput = null;
    /**
     * Callback to execute when the node failed to correctly
     * Enter description here ...
     * @var Closure
     */
    private $_executeOnInputFailed = null;
    /**
     * When true, the user may retry the input by pressing the cancel button
     * if and only if he/she has already input one or more digits.
     * @var boolean
     */
    private $_cancelWithInputRetriesInput = false;

    /**
     * Make pre prompt messages not interruptable
     *
     * @return Node
     */
    public function prePromptMessagesNotInterruptable()
    {
        $this->_prePromptMessagesInterruptable = false;
        return $this;
    }

    /**
     * Digits entered during the pre prompt messages are not considered
     * as node input.
     *
     * @return Node
     */
    public function dontAcceptPrePromptInputAsInput()
    {
        $this->_acceptPrePromptInputAsInput = false;
        return $this;
    }

    /**
     * Make prompt messages not interruptable.
     *
     * @return Node
     */
    public function unInterruptablePrompts()
    {
        $this->_promptsCanBeInterrupted = false;
        $this->_validInterruptDigits = '';
        return $this;
    }

    /**
     * Specify an optional message to play when the user did not enter any
     * input at all. Will NOT be played if this happens in the last allowed
     * attempt.
     *
     * @param string $filename Sound file to play.
     *
     * @return Node
     */
    public function playOnNoInput($filename)
    {
        $this->_onNoInputMessage = $filename;
        return $this;
    }

    /**
     * Optional message to play when the user exhausted all the available
     * attempts to enter a valid input.
     *
     * @param string $filename Sound file to play.
     *
     * @return Node
     */
    public function playOnMaxValidInputAttempts($filename)
    {
        $this->_onMaxValidInputAttempts = $filename;
        return $this;
    }

    /**
     * Specify a maximum attempt number for the user to enter a valid input.
     * Defaults to 1.
     *
     * @param integer $number
     *
     * @return Node
     */
    public function maxAttemptsForInput($number)
    {
        $this->_totalAttemptsForInput = $number;
        return $this;
    }

    /**
     * Given a callback and an optional sound to play on error, this will
     * return a validator information structure to be used with
     * validateInputWith().
     *
     * @param \Closure $validation Callback to use as validator
     * @param string|null $soundOnError Sound file to play on error
     *
     * @return validatorInfo
     */
    public static function createValidatorInfo(
        \Closure $validation, $soundOnError = null
    ) {
        return array(
            'callback' => $validation,
            'soundOnError' => $soundOnError
        );
    }

    /**
     * Given an array of validator information structures, this will load
     * all validators into this node.
     *
     * @param validatorInfo[] $validatorsInformation
     *
     * @return Node
     */
    public function loadValidatorsFrom(array $validatorsInformation)
    {
        foreach ($validatorsInformation as $name => $validatorInfo) {
            $this->validateInputWith(
                $name, $validatorInfo['callback'], $validatorInfo['soundOnError']
            );
        }
        return $this;
    }

    /**
     * Add an input validation to this node.
     *
     * @param string $name A distrinctive name for this validator
     * @param \Closure $validation Callback to use for validation
     * @param string|null $soundOnError Optional sound to play on error
     *
     * @return Node
     */
    public function validateInputWith($name, \Closure $validation, $soundOnError = null)
    {
        $this->_inputValidations[$name] = self::createValidatorInfo(
            $validation, $soundOnError
        );
        return $this;
    }

    /**
     * Calls all validators in order. Will stop when any of them returns false.
     *
     * @return boolean
     */
    public function validate()
    {
        foreach ($this->_inputValidations as $name => $data) {
            $validator = $data['callback'];
            $result = $validator($this);
            if ($result === false) {
                $this->logDebug("Validation FAILED: $name");
                $onError = $data['soundOnError'];
                if (is_array($onError)) {
                    foreach ($onError as $msg) {
                        $this->addPrePromptMessage($msg);
                    }
                } else {
                    $this->addPrePromptMessage($onError);
                }
                return false;
            }
            $this->logDebug("Validation OK: $name");
        }
        return true;
    }

    /**
     * Internally used to execute prompt messages in the agi client.
     *
     * @return void
     */
    protected function addClientMethodCall()
    {
        $args = func_get_args();
        $name = array_shift($args);
        $this->_promptMessages[] = array($name => $args);
    }

    /**
     * Internally used to execute pre prompt messages in the agi client.
     *
     * @return void
     */
    protected function addPrePromptClientMethodCall()
    {
        $args = func_get_args();
        $name = array_shift($args);
        $this->_prePromptMessages[] = array($name => $args);
    }

    /**
     * Adds a sound file to play as a pre prompt message.
     *
     * @param string $filename
     *
     * @return void
     */
    protected function addPrePromptMessage($filename)
    {
        $this->addPrePromptClientMethodCall(
        	'streamFile', $filename, $this->_validInterruptDigits
        );
    }

    /**
     * Loads a prompt message for saying the digits of the given number.
	 *
     * @param integer $digits
     *
     * @return Node
     */
    public function sayDigits($digits)
    {
        $this->addClientMethodCall('sayDigits', $digits, $this->_validInterruptDigits);
        return $this;
    }

    /**
     * Loads a prompt message for saying a number.
     *
     * @param integer $number
     *
     * @return Node
     */
    public function sayNumber($number)
    {
        $this->addClientMethodCall('sayNumber', $number, $this->_validInterruptDigits);
        return $this;
    }

    /**
     * Loads a prompt message for saying a date/time expressed by a unix
     * timestamp and a format.
     *
     * @param integer $timestamp
     * @param string $format
     *
     * @return Node
     */
    public function sayDateTime($timestamp, $format)
    {
        $this->addClientMethodCall('sayDateTime', $timestamp, $format, $this->_validInterruptDigits);
        return $this;
    }

    /**
     * Loads a prompt message for playing an audio file.
     *
     * @param string $filename
     *
     * @return Node
     */
    public function saySound($filename)
    {
        $this->addClientMethodCall('streamFile', $filename, $this->_validInterruptDigits);
        return $this;
    }

    /**
     * Configure the node to expect at least this many digits. The input is
     * considered complete when this many digits has been entered. Cancel and
     * end of input digits (if configured) are not taken into account.
     *
     * @param integer $length
     *
     * @return Node
     */
    public function expectAtLeast($length)
    {
        $this->_minInput = $length;
        return $this;
    }

    /**
     * Configure the node to expect at most this many digits. The reading loop
     * will try to read this many digits.
     *
     * @param integer $length
     *
     * @return Node
     */
    public function expectAtMost($length)
    {
        $this->_maxInput = $length;
        return $this;
    }

    /**
     * Configure this node to expect at least and at most this many digits.
     *
     * @param integer $length
     *
     * @return Node
     */
    public function expectExactly($length)
    {
        return $this->expectAtLeast($length)->expectAtMost($length);
    }

    /**
     * Configures a specific digit as the cancel digit.
     *
     * @param char $digit
     *
     * @return Node
     */
    public function cancelWith($digit)
    {
        $this->_cancelDigit = $digit;
        return $this;
    }

    /**
     * Configures a specific digit as the end of input digit.
     *
     * @param char $digit
     *
     * @return Node
     */
    public function endInputWith($digit)
    {
        $this->_endOfInputDigit = $digit;
        return $this;
    }

    /**
     * Configures the maximum time available between digits before a timeout.
     *
     * @param integer $milliseconds
     *
     * @return Node
     */
    public function maxTimeBetweenDigits($milliseconds)
    {
        $this->_timeBetweenDigits = $milliseconds;
        return $this;
    }

    /**
     * Configures the maximum time available for the user to enter valid input
     * per attempt.
     *
     * @param integer $milliseconds
     *
     * @return Node
     */
    public function maxTotalTimeForInput($milliseconds)
    {
        $this->_totalTimeForInput = $milliseconds;
        return $this;
    }

    /**
     * True if this node has at least this many digits entered.
     *
     * @param integer $length
     *
     * @return boolean
     */
    public function inputLengthIsAtLeast($length)
    {
        return strlen($this->_input) >= $length;
    }

    /**
     * True if this node has at least 1 digit as input, excluding cancel and
     * end of input digits.
     *
     * @return boolean
     */
    public function hasInput()
    {
        return $this->inputLenIsAtLeast(1);
    }

    /**
     * Returns input.
     *
     * @return string
     */
    public function getInput()
    {
        return $this->_input;
    }

    /**
     * Returns the agi client in use.
     *
     * @return \PAGI\Client\IClient
     */
    public function getClient()
    {
        return $this->_client;
    }

    /**
     * Gives a name for this node.
	 *
     * @param string $name
     *
     * @return Node
     */
    public function setName($name)
    {
        $this->_name = $name;
        return $this;
    }

    /**
     * Sets the pagi client to use by this node.
     *
     * @param \PAGI\Client\IClient $client
     *
     * @return Node
     */
    public function setAgiClient(IClient $client)
    {
        $this->_client = $client;
        return $this;
    }

    /**
     * Convenient method to create a node.
     *
     * @param string $name
     * @param PAGI\Client\IClient $agiClient
     *
     * @return Node
     */
    public static function create($name, $agiClient)
    {
        $node = new Node();
        return $node->setName($name)->setAgiClient($agiClient);
    }

    /**
     * Appends an input to the node input.
     *
     * @param char $digit
     *
     * @return void
     */
    protected function appendInput($digit)
    {
        $this->_input .= $digit;
    }

    /**
     * True if the digit matches the cancel digit.
     *
     * @param char $digit
     *
     * @return boolean
     */
    protected function inputIsCancel($digit)
    {
        return $digit == $this->_cancelDigit;
    }

    /**
     * True if the digit matches the end of input digit.
     *
     * @param char $digit
     *
     * @return boolean
     */
    protected function inputIsEnd($digit)
    {
        return $digit == $this->_endOfInputDigit;
    }

    /**
     * Returns the kind of digit entered by the user, CANCEL, END, NORMAL.
     *
     * @param char $digit
     *
     * @return integer
     */
    protected function evaluateInput($digit)
    {
        if ($this->inputIsCancel($digit)) {
            return self::INPUT_CANCEL;
        }
        if ($this->inputIsEnd($digit)) {
            return self::INPUT_END;
        }
        return self::INPUT_NORMAL;
    }

    /**
     * Process a single digit input by the user. Changes the node state
     * according to the digit entered (CANCEL, COMPLETE).
     *
     * @param char $digit
     *
     * @return void
     */
    protected function acceptInput($digit)
    {
        switch($this->evaluateInput($digit)) {
        case self::INPUT_CANCEL:
            $this->_state = self::STATE_CANCEL;
            break;
        case self::INPUT_END:
            $this->_state = self::STATE_COMPLETE;
            break;
        default:
            $this->appendInput($digit);
            if ($this->_minInput > 0 && $this->inputLengthIsAtLeast($this->_minInput)) {
                $this->_state = self::STATE_COMPLETE;
            }
        }
    }

    /**
     * True if the user reached the maximum allowed attempts for valid input.
     *
     * @return boolean
     */
    public function maxInputsReached()
    {
        return $this->_state == self::STATE_MAX_INPUTS_REACHED;
    }

    /**
     * True if this node is in CANCEL state.
     *
     * @return boolean
     */
    public function wasCancelled()
    {
        return $this->_state == self::STATE_CANCEL;
    }

    /**
     * True if this node is in TIMEOUT state.
     *
     * @return boolean
     */
    public function isTimeout()
    {
        return $this->_state == self::STATE_TIMEOUT;
    }

    /**
     * True if this node is in COMPLETE state.
     *
     * @return boolean
     */
    public function isComplete()
    {
        return $this->_state == self::STATE_COMPLETE;
    }

    /**
     * Calls methods in the PAGI client.
     *
     * @param methodInfo[] $methods Methods to call, an array of arrays. The
     * second array has the method name as key and an array of arguments as
     * value.
     * @param Closure $stopWhen If any, this callback is evaluated before
     * returning. Will return when false.
     *
     * @return IResult
     */
    protected function callClientMethods($methods, $stopWhen = null)
    {
        $result = null;
        foreach ($methods as $callInfo) {
            foreach ($callInfo as $name => $arguments) {
                $this->logDebug("$name(" . implode(",", $arguments) . ")");
                $result = call_user_func_array(
                    array($this->_client, $name), $arguments
                );
                if ($stopWhen !== null) {
                    if ($stopWhen($result)) {
                        return $result;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Plays pre prompt messages, like error messages from validations.
     *
     * @return IResult|null
     */
    protected function playPromptMessages()
    {
        $interruptable = $this->_promptsCanBeInterrupted;
        $result = $this->callClientMethods(
            $this->_promptMessages,
            function($result) use ($interruptable) {
                return $interruptable && !$result->isTimeout();
            }
        );
        return $result;
    }

    /**
     * Internally used to clear pre prompt messages after being played.
     *
     * @return void
     */
    protected function clearPrePromptMessages()
    {
        $this->_prePromptMessages = array();
    }

    /**
     * Internally used to play all pre prompt queued messages. Clears the
     * queue after it.
     *
     * @return \PAGI\Client\Result\IResult
     */
    protected function playPrePromptMessages()
    {
        $interruptable = $this->_prePromptMessagesInterruptable;
        $result = $this->callClientMethods(
            $this->_prePromptMessages,
            function($result) use ($interruptable) {
                return $interruptable && !$result->isTimeout();
            }
        );
        $this->clearPrePromptMessages();
        return $result;
    }

    /**
     * Internally used to clear the input per input attempt. Also resets state
     * to TIMEOUT.
     *
	 * @return Node
     */
    protected function resetInput()
    {
        $this->_state = self::STATE_TIMEOUT;
        $this->_input = '';
        return $this;
    }

    /**
     * Internally used to accept input from the user. Plays pre prompt messages,
     * prompt, and waits for a complete input or cancel.
     *
     * @return void
     */
    protected function doInput()
    {
        $this->resetInput();
        $result = $this->playPrePromptMessages();
        if (!$this->_acceptPrePromptInputAsInput) {
            $result = $this->playPromptMessages();
            if ($result !== null && $result->isTimeout()) {
                $this->acceptInput($result->getDigits());
            }
        } else if ($result !== null && !$result->isTimeout()) {
            $this->acceptInput($result->getDigits());
        } else {
            $result = $this->playPromptMessages();
            if ($result !== null) {
                $this->acceptInput($result->getDigits());
            }
        }
        if ($this->isComplete() || $this->wasCancelled()) {
            return;
        }
        $len = strlen($this->_input);
        $start = time();
        for ($i = $len; $i < $this->_maxInput; $i++) {
            if ($this->_totalTimeForInput != -1) {
                $totalElapsedTime = (time() - $start) * 1000;
                if ($totalElapsedTime >= $this->_totalTimeForInput) {
                    $this->logDebug("Expired total available time for input");
                    break;
                }
            }
            $this->logDebug($this->_name . ": Reading Digit #: " . ($i + 1));
            $result = $this->_client->waitDigit($this->_timeBetweenDigits);
            if ($result->isTimeout()) {
                $this->logDebug("Expired available time per digit");
                break;
            }
            $input = $result->getDigits();
            $this->acceptInput($input);
            if ($this->inputIsEnd($input) || $this->wasCancelled()) {
                break;
            }
        }
    }

    /**
     * Saves a custom key/value to the registry.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return Node
     */
    public function saveCustomData($key, $value)
    {
        $this->_registry[$key] = $value;
        return $this;
    }

    /**
     * Returns the value for the given key in the registry.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getCustomData($key)
    {
        return $this->_registry[$key];
    }

    /**
     * True if the given key exists in the registry.
     *
     * @param string $key
     *
     * @return boolean
     */
    public function hasCustomData($key)
    {
        return isset($this->_registry[$key]);
    }

    /**
     * Remove a key/value from the registry.
     *
     * @param string $key
     *
     * @return Node
     */
    public function delCustomData($key)
    {
        unset($this->_registry[$key]);
        return $this;
    }

    /**
     * Allow the user to retry input by pressing the cancel digit after entered
     * one or more digits. For example, when entering a 12 number pin, the user
     * might press the cancel digit at the 5th digit to re-enter it. This
     * counts as a failed input, but will not cancel the node. The node will be
     * cancelled only if the user presses the cancel digit with NO input at all.
     *
     * @return Node
     */
    public function cancelWithInputRetriesInput()
    {
        $this->_cancelWithInputRetriesInput = true;
        return $this;
    }

    /**
     * Specify a callback function to invoke when the user entered a valid
     * input.
     *
     * @param \Closure $callback
     *
     * @return Node
     */
    public function executeOnValidInput(\Closure $callback)
    {
        $this->_executeOnValidInput = $callback;
        return $this;
    }

    /**
     * Executes a callback when the node fails to properly get input from the
     * user (either because of cancel, max attempts reached, timeout).
     *
     * @param \Closure $callback
     *
     * @return Node
     */
    public function executeOnInputFailed(\Closure $callback)
    {
        $this->_executeOnInputFailed = $callback;
        return $this;
    }

    /**
     * Executes this node.
     *
     * @return Node
     */
    public function run()
    {
        if (!($this->_client instanceof \PAGI\Client\IClient)) {
            throw new NodeException("Need a PAGI client to work with");
        }
        for ($attempts = 0; $attempts < $this->_totalAttemptsForInput; $attempts++) {
            $this->doInput();
            if ($this->wasCancelled()) {
                if ($this->_cancelWithInputRetriesInput && $this->inputLengthIsAtLeast(1)) {
                    $this->logDebug("Cancelled input, retrying");
                    continue;
                }
                $this->logDebug("Cancelled node, quitting");
                break;
            }
            if (
                $this->_onNoInputMessage !== null
                && !$this->inputLengthIsAtLeast(1)
                && $attempts != $this->_totalAttemptsForInput
            ) {
                $this->addPrePromptMessage($this->_onNoInputMessage);
                continue;
            }
            if ($this->inputLengthIsAtLeast(1) && $this->validate()) {
                $this->logDebug("Input validated");
                if ($this->_executeOnValidInput !== null) {
                    $callback = $this->_executeOnValidInput;
                    $callback($this);
                }
                break;
            }
        }
        if ($this->_minInput > 0 && $attempts == $this->_totalAttemptsForInput) {
            $this->logDebug("Max attempts reached");
            $this->_state = self::STATE_MAX_INPUTS_REACHED;
            if ($this->_onMaxValidInputAttempts !== null) {
                $this->callClientMethods(array(
                    array('streamFile' => array($this->_onMaxValidInputAttempts, self::DTMF_ANY))
                ));
            }
        }
        if (!$this->isComplete() && $this->_executeOnInputFailed !== null) {
            $callback = $this->_executeOnInputFailed;
            $callback($this);
        }
        $this->logDebug($this);
        return $this;
    }

    /**
     * Used internally to log debug messages
     *
     * @param string $msg
     *
     * @return void
     */
    protected function logDebug($msg)
    {
        $logger = $this->_client->getAsteriskLogger();
        $ani = $this->_client->getChannelVariables()->getCallerIdName();
        $dnis = $this->_client->getChannelVariables()->getDNIS();
        $logger->debug($this->_name . ": $ani -> $dnis: $msg");
    }

    /**
     * Maps the current node state to a human readable string.
     *
     * @return string
     */
    protected function stateToString()
    {
        switch ($this->_state) {
        case self::STATE_CANCEL:
            return "cancel";
        case self::STATE_COMPLETE:
            return "complete";
        case self::STATE_NOT_RUN:
            return "not run";
        case self::STATE_TIMEOUT:
            return "timeout";
        case self::STATE_MAX_INPUTS_REACHED:
            return "max valid input attempts reached";
        default:
            return "???";
        }
    }

    /**
     * A classic!
     *
     * @return string
     */
    public function __toString()
    {
        return
            "[ Node: " . $this->_name
            . " input: (" . $this->_input . ") "
            . " state: (" . $this->stateToString() . ")"
            . "]"
        ;
    }
}
