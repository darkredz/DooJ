<?php
/**
 * DooMasterSlave class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */


/**
 * DooMasterSlave is an ORM tool based on DooSqlMagic with Database Replication support.
 *
 * <p>This class handles Master-Slave connections. It automatically handle CRUD operations with appropriate slave/master DB server.
 * You can handle thee connections manually by using useConnection, connectMaster, and connectSlave.</p>
 *
 * <p>DooMasterSlave <b>DOES NOT</b> send SELECT statement to a random slave. Instead, it is based on calculation with both access time and Slave nodes.</p>
 *
 * <p>To use DB replication, you would have to setup the slave servers in <b>db.conf.php</b></p>
 *
 * <code>
 * //This will serve as the Master
 * $dbconfig['dev'] = array('localhost', 'db', 'root', '1234', 'mysql',true);
 *
 * //slave with the same info as master
 * $dbconfig['slave'] = array('192.168.1.1', '192.168.1.2', '192.168.1.3');
 *
 * //OR ...
 * //slave with different info, use a string if it's same as the master info.
 * $dbconfig['slave'] = array(
 *                      array('192.168.1.1', 'db', 'dairy', '668dj0', 'mysql',true),
 *                      array('192.168.1.2', 'db', 'yuhus', 'gu34k2', 'mysql',true),
 *                      array('192.168.1.3', 'db', 'lily', '84ju2a', 'mysql',true),
 *                      '192.168.1.4'
 *                   );
 * </code>
 *
 * <p>In the bootstrap index.php you would need to call the <b>useDbReplicate</b> method.</p>
 * <code>
 * Doo::useDbReplicate();
 * Doo::db()->setMap($dbmap);
 * Doo::db()->setDbConfig($dbconfig, $config['APP_MODE']);
 * </code>
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooMasterSlave.php 1000 2009-08-20 22:53:26
 * @package doo.db
 * @since 1.1
 */

class DooMasterSlave extends DooSqlMagic
{

    const MASTER = 'master';
    const SLAVE = 'slave';

    /**
     * Stores the pdo connection for master & slave
     * @var array
     */
    protected $pdoList = [];

    protected $autoToggle = true;

    /**
     * Connects to the database with the default slaves configurations
     */
    public function connect($master = false)
    {
        if ($this->config == null) {
            return;
        }

        if ($master) {
            $this->connectMaster();
            return;
        }

        if (!isset($this->configList[$this->configNameUsed]['slave'])) {
            return;
        }

        $slaves = $this->configList[$this->configNameUsed]['slave'];
        $totalSlaves = sizeof($slaves);

        $time = round(microtime(true), 2) * 100;
        $time = substr($time, strlen($time) - 2);

        $sessionToHandle = 100 / $totalSlaves;

        $choosenSlaveIndex = floor($time / $sessionToHandle);

        $choosenSlave = $slaves[$choosenSlaveIndex];

        $this->app->logDebug("DB Connect Slave {$choosenSlave[4]}:host={$choosenSlave[0]};dbname={$choosenSlave[1]}");
        try {
            $this->pdo = new PDO("{$choosenSlave[4]}:host={$choosenSlave[0]};dbname={$choosenSlave[1]}",
                $choosenSlave[2], $choosenSlave[3], [PDO::ATTR_PERSISTENT => $choosenSlave[5]]);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connected = true;
        } catch (PDOException $e) {
            throw new SqlMagicException('Failed to open the DB connection', SqlMagicException::DB_CONN_ERROR);
        }

        $this->pdoList[1] = $this->pdo;
        $this->connected = true;
    }

    /**
     * Connects to a slave.
     *
     * Choose an index of the slave configuration to connect as defined in db.conf.php
     * @param int $slaveIndex
     */
    public function connectSlave($slaveIndex)
    {
        $choosenSlave = $this->configList[$this->configNameUsed]['slave'][$slaveIndex];
        try {
            $this->pdo = new PDO("{$choosenSlave[4]}:host={$choosenSlave[0]};dbname={$choosenSlave[1]}",
                $choosenSlave[2], $choosenSlave[3], [PDO::ATTR_PERSISTENT => $choosenSlave[5]]);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connected = true;
        } catch (PDOException $e) {
            throw new SqlMagicException('Failed to open the DB connection', SqlMagicException::DB_CONN_ERROR);
        }
        $this->pdoList[1] = $this->pdo;
    }

    /**
     * Connects to the database with the default database configurations (master)
     */
    public function connectMaster()
    {
        $masterConfig = $this->config['master'];
        $this->app->logDebug("DB Connect Master {$masterConfig[4]}:host={$masterConfig[0]};dbname={$masterConfig[1]}");
        if (isset($this->pdoList[0])) {
            $this->pdoList[0];
            return;
        }
        try {
            $this->pdo = new PDO("{$masterConfig[4]}:host={$masterConfig[0]};dbname={$masterConfig[1]}",
                $masterConfig[2], $masterConfig[3], [PDO::ATTR_PERSISTENT => $masterConfig[5]]);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connected = true;
        } catch (PDOException $e) {
            throw new SqlMagicException('Failed to open the DB connection', SqlMagicException::DB_CONN_ERROR);
        }
        $this->pdoList[0] = $this->pdo;
    }

    /**
     * Set connection to use slaves or master.
     *
     * If you use this method, you would have to handle the connection (slave/master) manually for the all query codes after this call.
     *
     * @param string $mode Either 'master' or 'slave'
     */
    public function useConnection($mode)
    {
        if ($mode == 'master') {
            if (!isset($this->pdoList[0])) {
                $this->connectMaster();
            } else {
                $this->pdo = $this->pdoList[0];
            }
        } else {
            if ($mode == 'slave') {
                $this->pdo = $this->pdoList[1];
            }
        }
        $this->autoToggle = false;
    }

    /**
     * Execute a query to the connected database. Auto toggle between master & slave.
     *
     * @param string $query SQL query prepared statement
     * @param array $param Values used in the prepared SQL
     * @return PDOStatement
     */
    public function query($query, $param = null)
    {
        if ($this->autoToggle === true) {
            $isSelect = intval(strtoupper(substr($query, 0, 6)) == 'SELECT' && substr($query, -10) != 'FOR UPDATE');

            $useSlave = $isSelect && isset($this->configList[$this->configNameUsed]) && isset($this->configList[$this->configNameUsed]['slave']) && !empty($this->configList[$this->configNameUsed]['slave']);

            //change to master if update, insert, delete, create connection if not exist
            if ($useSlave) {
                if (!isset($this->pdoList[$isSelect])) {
                    $this->app->logInfo('connecting to slave');
                    $this->connect();
                } else {
                    $this->app->logInfo('connecting to existing slave');
                    $this->pdo = $this->pdoList[$isSelect];
                }
            } else {
                if (!isset($this->pdoList[$isSelect])) {
                    $this->app->logInfo('connecting to master');
                    $this->connectMaster();
                } else {
                    $this->app->logInfo('connecting to existing master');
                    $this->pdo = $this->pdoList[$isSelect];
                }
            }
        }
        return parent::query($query, $param);
    }
}
