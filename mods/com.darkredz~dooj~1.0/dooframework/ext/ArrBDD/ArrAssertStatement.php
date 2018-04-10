<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 08/04/2018
 * Time: 11:49 PM
 */

class ArrAssertStatement
{
    protected $subject;
    protected $statements = [];
    protected $appendStatementKeyword = true;

    function __construct($appendStatementKeyword = true)
    {
        $this->appendStatementKeyword = $appendStatementKeyword;
    }

    public static function make($appendStatementKeyword = true)
    {
        $assert = new ArrAssertStatement($appendStatementKeyword);
        return $assert;
    }

    public function getStatements()
    {
        return $this->statements;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function subject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    public function add($shouldStatement, $assertResult)
    {
        if ($this->appendStatementKeyword) {
            $shouldStatement = 'SHOULD ' . $shouldStatement;
        }
        $this->statements[$shouldStatement] = $assertResult;
        return $this;
    }

    public function should($shouldStatement)
    {
        if ($this->appendStatementKeyword) {
            $shouldStatement = 'SHOULD ' . $shouldStatement;
        }
        $this->statements[$shouldStatement] = null;
        return $this;
    }

    public function with($assertResult)
    {
        $keys = array_keys($this->statements);
        $shouldStatement = $keys[sizeof($keys) - 1];
        $this->statements[$shouldStatement] = $assertResult;
        return $this;
    }
}