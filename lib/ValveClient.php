<?php
/**
 * @package NetworkClients
 * @subpackage HLClient
 *
 * @author Zorin Vasily <kak.serpom.po.yaitsam@gmail.com>
 */
// https://developer.valvesoftware.com/wiki/Server_queries
class ValveClient extends NetworkClient {
	const A2S_INFO = "\x54";
	const S2A_INFO = "\x49";
	const A2S_PLAYER = "\x55";
	const S2A_PLAYER = "\x44";
	const A2S_SERVERQUERY_GETCHALLENGE = "\x57";
	const S2A_SERVERQUERY_GETCHALLENGE = "\x41";
	const A2A_PING = "\x69";
	const S2A_PONG = "\x6A";

	public function request($addr, $name, $data,  $cb) {
		$e = explode(':', $addr);
		$this->getConnection('valve://[udp:' . $e[0] . ']' . (isset($e[1]) ? ':'.$e[1] : '') . '/', function($conn) use ($cb, $addr, $data, $name) {
			if (!$conn->connected) {
				call_user_func($cb, false);
				return false;
			}
			$conn->request($name, $data, $cb);
		});
	}
	
	public function requestPing($addr, $cb) {
		$mt = microtime(true);
		$this->request($addr, 'ping', null, function ($conn, $latency) use ($mt, $cb) {
			call_user_func($cb, $this, microtime(true) - $mt);
		});	
	}

	public function requestInfo($addr, $cb) {
		$this->request($addr, 'info', null, $cb);
	}

	public function requestPlayers($addr, $cb) {
		$this->request($addr, 'challenge', null, function ($conn, $challenge) use ($cb) {
			$conn->request('players', $challenge, $cb);
		});
	}

	/**
	 * Setting default config options
	 * Overriden from NetworkClient::getConfigDefaults
	 * @return array|false
	 */
	protected function getConfigDefaults() {
		return array(
			// @todo add description strings
			'servers'               =>  '127.0.0.1',
			'port'					=> 27015,
			'maxconnperserv'		=> 1
		);
	}
}
class ValveClientConnection extends NetworkClientConnection {
	public $timeout = 10;

	public function request($name, $data = null, $cb = null) {
		$packet = "\xFF\xFF\xFF\xFF";
		if ($name === 'ping') {
			$packet .= ValveClient::A2A_PING;
		} elseif ($name === 'challenge') {
			$packet .= ValveClient::A2S_SERVERQUERY_GETCHALLENGE;
		} elseif ($name === 'info') {
			$packet .= ValveClient::A2S_INFO . "Source Engine Query\x00";
			//"\xFF\xFF\xFF\xFFdetails\x00"
		} elseif ($name === 'players') {
			$packet .= ValveClient::A2S_PLAYER . $data;
		} else {
			return false;
		}
		$this->onResponse->push($cb);
		$this->setFree(false);
   		$this->write($packet);
	}

	/**
	 * Called when new data received
	 * @param string New data
	 * @return void
	 */
	public function stdin($buf) {
		$this->buf .= $buf;
		start:
		if (strlen($this->buf) < 5) {
			return;
		}
		$h = Binary::getDWord($this->buf);
		if ($h !== 0xFFFFFFFF) {
			$this->finish();
			return;
		}
		$type = Binary::getChar($this->buf);
		if ($type === ValveClient::S2A_INFO) {
			$result = $this->parseInfo($this->buf);
		}
		elseif ($type === ValveClient::S2A_PLAYER) {
			$result = $this->parsePlayers($this->buf);
		}
		elseif ($type === ValveClient::S2A_SERVERQUERY_GETCHALLENGE) {
			$result = binarySubstr($this->buf, 0, 5);
			$this->buf = binarySubstr($this->buf, 5);
		}
		elseif ($type === ValveClient::S2A_PONG) {
			$result =  null;
		}
		else {
			$result = null;
		}
		$this->onResponse->executeOne($this, $result);
		goto start;
	}

	public function parsePlayers(&$st) {
		$playersn = Binary::getByte($st);
		for ($i = 1; $i < $playersn; ++$i) {
			$n = Binary::getByte($st);
			$name = Binary::getString($st);
			$score = Binary::getDWord($st,TRUE);
			if (strlen($st) === 0) {
				break;
			}
			$u = unpack('f', binarySubstr($st, 0, 4));
			$st = binarySubstr($st, 4);
			$time = $u[1];
			if ($time == -1) {
				continue;
			}
			$players[] = array(
				'name' => $name,
				'score' => $score,
				'time' => $time
			);
		}
		return $players;
	}

	public function parseInfo(&$st) {
		$h = Binary::getDWord($st);
   		$t = Binary::getChar($st);
		$info = array();
		if ($t == 'I') {// Source
			$info['proto'] = Binary::getByte($st);   
			$e = explode(':',$this->addr);
			$info['address'] = $e[0];
			$info['hostname'] = Binary::getString($st);
			$info['map'] = Binary::getString($st);
			$info['gamedir'] = Binary::getString($st);
			$info['gamedescr'] = Binary::getString($st);
			$info['steamid'] = Binary::getWord($st);
			$info['playersnum'] = Binary::getByte($st);
			$info['playersmax'] = Binary::getByte($st); 
			$info['botcount'] = Binary::getByte($st); 
			$info['servertype'] = Binary::getChar($st); 
			$info['serveros'] = Binary::getChar($st); 
			$info['passworded'] = Binary::getByte($st); 
			$info['secure'] = Binary::getByte($st); 
   		}
   		elseif ($t == 'm') {
    		$info['address'] = Binary::getString($st);
    		$info['hostname'] = Binary::getString($st);
    		$info['map'] = Binary::getString($st);
    		$info['gamedir'] = Binary::getString($st);
    		$info['gamedescr'] = Binary::getString($st);
			$info['playersnum'] = Binary::getByte($st);
			$info['playersmax'] = Binary::getByte($st); 
			$info['proto'] = Binary::getByte($st);
			$info['servertype'] = Binary::getChar($st); 
			$info['serveros'] = Binary::getChar($st); 
			$info['passworded'] = Binary::getByte($st); 
			$info['modded'] = Binary::getByte($st); 
			if ($info['modded']) {
				$info['mod_website'] = Binary::getString($st);
				$info['mod_downloadserver'] = Binary::getString($st);
				$info['mod_unused'] = Binary::getString($st);
				$info['mod_version'] = Binary::getDWord($st,TRUE);
				$info['mod_size'] = Binary::getDWord($st);
				$info['mod_serverside'] = Binary::getByte($st);
				$info['mod_customdll'] = Binary::getByte($st);
    		}
			$info['secure'] = Binary::getByte($st);
		}
		return $info;
	}
}