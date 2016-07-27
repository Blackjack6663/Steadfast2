<?php

namespace proxy;

class ProxySocket {

	private $address;
	private $port;
	private $socket;
	private $lastMessage = '';
	private $server;

	public function __construct($server, $address, $port) {
		$this->server = $server;
		$this->address = $address;
		$this->port = $port;
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if (!@socket_connect($this->socket, $address, $port)) {
			throw new \Exception('Socket can\'t connect');
		}
		socket_set_nonblock($this->socket);
	}

	public function getIdentifier() {
		return $this->address . $this->port;
	}

	public function writeMessage($msg) {
		if (strlen($msg) > 0) {
			$data = zlib_encode($msg, ZLIB_ENCODING_DEFLATE, 7);
			socket_write($this->socket, pack('N', strlen($data)) . $data);
		}
	}

	public function checkMessages() {
		$data = $this->lastMessage;
		$this->lastMessage = '';
		while (strlen($buffer = @socket_read($this->socket, 65535, PHP_BINARY_READ)) > 0) {
			$data .= $buffer;
		}
		if (($dataLen = strlen($data)) > 0) {
			$offset = 0;
			while ($offset < $dataLen) {
				if ($offset + 4 > $dataLen) {
					$this->lastMessage = substr($data, $offset);
					break;
				}
				$len = unpack('N', substr($data, $offset, 4));
				$len = $len[1];
				if ($offset + $len + 4 > $dataLen) {
					$this->lastMessage = substr($data, $offset);
					break;
				}
				$offset += 4;
				$msg = substr($data, $offset, $len);
				$this->checkPacket($msg);
				$offset += $len;
			}
		}
	}

	private function checkPacket($buffer) {
		$buffer = zlib_decode($buffer);
		$id = unpack('N', substr($buffer, 0, 4));
		$id = $id[1];
		$buffer = substr($buffer, 4);
		$type = ord($buffer{0});
		$buffer = substr($buffer, 1);
		$this->server->checkPacket($id, $buffer, $type);
	}

	

}
