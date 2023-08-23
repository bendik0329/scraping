<?php
class Database
{
  private $connection;
  private $numQueries = 0;
  private $lastId;
  private $affectedRows;
  private $query;
  private $result;

  public function connect($dbHost, $dbUser, $dbPass, $dbName)
  {
    $this->connection = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

    if ($this->connection->connect_errno) {
      die('DB CONNECT ERROR: (' . $this->connection->connect_errno . ') ' . $this->connection->connect_error);
    }

    return $this->connection;
  }

  public function close()
  {
    if ($this->connection) {
      if ($this->query) {
        $this->query->free_result();
      }
      $result = $this->connection->close();
      return $result;
    } else {
      return false;
    }
  }

  public function query($query)
  {
    if ($query != '') {
      unset($this->result);

      $this->result = $this->connection->query($query);
      $this->lastId = $this->connection->insert_id;
      $this->affectedRows = $this->connection->affected_rows;
      $this->numQueries++;
    }

    return $this->result;
  }

  public function numRows($stream)
  {
    if ($stream) {
      return $stream->num_rows;
    } else {
      return false;
    }
  }

  public function getLastID()
  {
    return $this->lastId;
  }

  public function getAffectedRows()
  {
    return $this->affectedRows;
  }

  public function makeSafe($string)
  {
    return addslashes(trim($string));
  }

  public function fetchArray($stream = null)
  {
    if (!$stream) {
      $stream = $this->result;
    }

    return $stream->fetch_assoc();
  }

  public function fetchRow($stream = null)
  {
    if ($stream) {
      return $stream->fetch_row();
    } else {
      return false;
    }
  }

  public function freeResult($queryStream)
  {
    if ($queryStream) {
      $queryStream->free_result();
      return true;
    } else {
      return false;
    }
  }
}
