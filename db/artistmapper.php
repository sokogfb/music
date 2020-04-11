<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2016 - 2020
 */

namespace OCA\Music\Db;

use OCP\IDBConnection;

class ArtistMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_artists', '\OCA\Music\Db\Artist');
	}

	/**
	 * @param string $condition
	 */
	private function makeSelectQuery($condition=null) {
		return 'SELECT `artist`.`name`, `artist`.`image`, `artist`.`id`, '.
			'`artist`.`mbid`, `artist`.`hash` FROM `*PREFIX*music_artists` `artist` '.
			'WHERE `artist`.`user_id` = ? ' . $condition;
	}

	/**
	 * @param string $userId
	 * @param integer $sortBy sort order of the result set
	 * @param integer|null $limit
	 * @param integer|null $offset
	 * @return Artist[]
	 */
	public function findAll($userId, $sortBy=SortBy::None, $limit=null, $offset=null) {
		$sql = $this->makeSelectQuery(
				$sortBy == SortBy::Name ? 'ORDER BY LOWER(`artist`.`name`)' : null);
		$params = [$userId];
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @param string $userId
	 * @param integer $sortBy sort order of the result set
	 * @return Artist[]
	 */
	public function findAllHavingAlbums($userId, $sortBy=SortBy::None) {
		$sql = $this->makeSelectQuery('AND EXISTS '.
				'(SELECT 1 FROM `*PREFIX*music_albums` `album` '.
				' WHERE `artist`.`id` = `album`.`album_artist_id`)');

		if ($sortBy == SortBy::Name) {
			$sql .= ' ORDER BY LOWER(`artist`.`name`)';
		}

		$params = [$userId];
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param integer[] $artistIds
	 * @param string $userId
	 * @return Artist[]
	 */
	public function findMultipleById($artistIds, $userId) {
		$sql = $this->makeSelectQuery('AND `artist`.`id` IN '
			. $this->questionMarks(\count($artistIds))
			. ' ORDER BY LOWER(`artist`.`name`)');
		$params = $artistIds;
		\array_unshift($params, $userId);
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param string|null $artistName
	 * @param string $userId
	 * @param bool $fuzzy
	 */
	protected function makeFindByNameSqlAndParams($artistName, $userId, $fuzzy = false) {
		if ($artistName === null) {
			$condition = 'AND `artist`.`name` IS NULL';
			$params = [$userId];
		} elseif ($fuzzy) {
			$condition = 'AND LOWER(`artist`.`name`) LIKE LOWER(?)';
			$params = [$userId, '%' . $artistName . '%'];
		} else {
			$condition = 'AND `artist`.`name` = ?';
			$params = [$userId, $artistName];
		}
		$sql = $this->makeSelectQuery($condition . ' ORDER BY LOWER(`artist`.`name`)');
		return [
			'sql' => $sql,
			'params' => $params,
		];
	}

	/**
	 * @param string|null $artistName
	 * @param string $userId
	 * @param bool $fuzzy
	 * @param integer|null $limit
	 * @param integer|null $offset
	 * @return Artist[]
	 */
	public function findAllByName($artistName, $userId, $fuzzy = false, $limit=null, $offset=null) {
		$sqlAndParams = $this->makeFindByNameSqlAndParams($artistName, $userId, $fuzzy);
		return $this->findEntities($sqlAndParams['sql'], $sqlAndParams['params'], $limit, $offset);
	}

	/**
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param Artist $artist
	 * @return Artist
	 */
	protected function findUniqueEntity($artist) {
		return $this->findEntity(
				'SELECT * FROM `*PREFIX*music_artists` WHERE `user_id` = ? AND `hash` = ?',
				[$artist->getUserId(), $artist->getHash()]
		);
	}
}
