<?php

/**************************************************************************/
/* phpDaemon
/* Web: http://github.com/kakserpom/phpdaemon
/* ===========================
/* @class ThreadCollection
/* @author kak.serpom.po.yaitsam@gmail.com
/* @description Collection of threads
/**************************************************************************/


class ThreadCollection {

	/**
	 * Array of threads
	 * @var array
	 */
	public $threads = array();

	/**
	 * FIXME: Add a description
	 */
	public $waitstatus;

	/**
	 * FIXME: Add a description
	 * @var int
	 */
	public $spawncounter = 0;

	/**
	 * Pushes certain thread to the collection
	 * @param object Thread to push
	 * @return void
	 */
	public function push($thread) {
		++$this->spawncounter;
		$thread->spawnid = $this->spawncounter;
		$this->threads[$thread->spawnid] = $thread;
	}

	/**
	 * Start all collected threads
	 * @return void
	 */
	public function start() {
		foreach ($this->threads as $thread) {
			$thread->start();
		}
	}

	/**
	 * Stop all collected threads
	 * @param boolean Kill?
	 * @return void
	 */
	public function stop($kill = false) {
		foreach ($this->threads as $thread) {
			$thread->stop($kill);
		}
	}

	/**
	 * Return the collected threads count
	 * @return integer Count
	 */
	public function getCount() {
		return sizeof($this->threads);
	}

	/**
	 * Remove terminated threads from the collection
	 * @param boolean Whether to check the threads using signal
	 * @return integer Removed threads count
	 */
	public function removeTerminated($check = FALSE) {
		$n = 0;

		foreach ($this->threads as $k => &$t) {
			if (
				$t->terminated
				|| (
					$check
					&& !$t->signal(SIGTTIN)
				)
			) {
				unset($this->threads[$k]);
				$n++;
			}
		}

		return $n;
	}

	/**
	 * Send a signal to threads
	 * @param integer Signal's number
	 * @return void
	 */
	public function signal($sig) {
		foreach ($this->threads as $thread) {
			$thread->signal($sig);
		}
	}
}
