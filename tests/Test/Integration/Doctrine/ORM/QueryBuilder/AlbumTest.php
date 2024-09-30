<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\QueryBuilder;

use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

use function count;

use const PHP_INT_MAX;

class AlbumTest extends AbstractIntegrationTestCase
{
    public function testSelect(): void
    {
        $qb = $this->_entityManager->createQueryBuilder();
        $qb
            ->select('album')
            ->from(Entity\Album::class, 'album');
        $expectedDQL = 'SELECT album FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';
        $qb->getQuery()->getDQL();
        $qb->getQuery()->getSQL();
        self::assertSame($expectedDQL, $qb->getQuery()->getDQL());
        $expectedSQL  = 'SELECT a0_.id AS ID_0, a0_.timeCreated AS TIMECREATED_1, a0_.name AS NAME_2,';
        $expectedSQL .= ' a0_.artist_id AS ARTIST_ID_3 FROM ALBUM a0_';
        self::assertSame($expectedSQL, $qb->getQuery()->getSQL());
        $albums = $qb->getQuery()->getResult();
        self::assertIsArray($albums);
        self::assertArrayHasKey(0, $albums);
        self::assertInstanceOf(Entity\Album::class, $albums[0]);

        self::assertSame(1, $albums[0]->getId());
    }

    public function testSelectColumn(): void
    {
        $qb = $this->_entityManager->createQueryBuilder();
        $qb
            ->select('album.id')
            ->from(Entity\Album::class, 'album');
        $expectedDQL = 'SELECT album.id FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';

        self::assertSame($expectedDQL, $qb->getQuery()->getDQL());
        $expectedSQL = 'SELECT a0_.id AS ID_0 FROM ALBUM a0_';
        self::assertSame($expectedSQL, $qb->getQuery()->getSQL());
        $ids = $qb->getQuery()->getResult();
        self::assertIsArray($ids);
        self::assertGreaterThan(0, count($ids));
        self::assertArrayHasKey(0, $ids);
        self::assertArrayHasKey('id', $ids[0]);
        self::assertSame(1, $ids[0]['id']);
    }

    public function testSelectWithJoin(): void
    {
        $qb = $this->_entityManager->createQueryBuilder();
        $qb
            ->select('album')
            ->from(Entity\Album::class, 'album')
            ->join('album.artist', 'artist');
        // Inherited join
        $expectedDQL = 'SELECT album FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';

        $expectedDQL .= ' INNER JOIN album.artist artist';
        self::assertSame($expectedDQL, $qb->getQuery()->getDQL());
        $expectedSQL  = 'SELECT a0_.id AS ID_0, a0_.timeCreated AS TIMECREATED_1, a0_.name AS NAME_2,';
        $expectedSQL .= ' a0_.artist_id AS ARTIST_ID_3 FROM ALBUM a0_ INNER JOIN ARTIST a1_ ON a0_.artist_id = a1_.id';
        self::assertSame($expectedSQL, $qb->getQuery()->getSQL());
        $albums = $qb->getQuery()->getResult();
        self::assertIsArray($albums);
        self::assertArrayHasKey(0, $albums);
        self::assertInstanceOf(Entity\Album::class, $albums[0]);

        self::assertSame(1, $albums[0]->getId());
        self::assertSame(2, $albums[0]->getArtist()->getId());
    }

    public function testSelectWithManualJoin(): void
    {
        $qb          = $this->_entityManager->createQueryBuilder();
        $expectedDQL = 'SELECT album FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';
        $qb
            ->select('album')
            ->from(Entity\Album::class, 'album')
            ->join(Entity\Artist::class, 'artist', 'WITH', 'artist = album.artist');
        // Manual join
        $expectedDQL .= ' INNER JOIN Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Artist artist WITH artist = album.artist';

        self::assertSame($expectedDQL, $qb->getQuery()->getDQL());
        $expectedSQL  = 'SELECT a0_.id AS ID_0, a0_.timeCreated AS TIMECREATED_1, a0_.name AS NAME_2,';
        $expectedSQL .= ' a0_.artist_id AS ARTIST_ID_3 FROM ALBUM a0_';
        $expectedSQL .= ' INNER JOIN ARTIST a1_ ON (a1_.id = a0_.artist_id)';
        self::assertSame($expectedSQL, $qb->getQuery()->getSQL());
        $albums = $qb->getQuery()->getResult();
        self::assertIsArray($albums);
        self::assertArrayHasKey(0, $albums);
        self::assertInstanceOf(Entity\Album::class, $albums[0]);

        self::assertSame(1, $albums[0]->getId());
        self::assertSame(2, $albums[0]->getArtist()->getId());
    }

    public function testSelectWithLeftJoinWhereJoinedElementExists(): void
    {
        $qb = $this->_entityManager->createQueryBuilder();
        $qb
            ->select('album')
            ->from(Entity\Album::class, 'album')
            ->leftJoin('album.artist', 'artist');
        $expectedDQL = 'SELECT album FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';

        $expectedDQL .= ' LEFT JOIN album.artist artist';
        self::assertSame($expectedDQL, $qb->getQuery()->getDQL());
        $expectedSQL  = 'SELECT a0_.id AS ID_0, a0_.timeCreated AS TIMECREATED_1, a0_.name AS NAME_2,';
        $expectedSQL .= ' a0_.artist_id AS ARTIST_ID_3 FROM ALBUM a0_ LEFT JOIN ARTIST a1_ ON a0_.artist_id = a1_.id';
        self::assertSame($expectedSQL, $qb->getQuery()->getSQL());
        $albums = $qb->getQuery()->getResult();
        self::assertIsArray($albums);
        self::assertArrayHasKey(0, $albums);
        self::assertInstanceOf(Entity\Album::class, $albums[0]);

        self::assertSame(1, $albums[0]->getId());
        self::assertSame(2, $albums[0]->getArtist()->getId());
    }

    public function testSelectWithLeftJoinWhereJoinedElementIsNull(): void
    {
        $qb = $this->_entityManager->createQueryBuilder();
        $qb
            ->select('album', 'artist')
            ->from(Entity\Album::class, 'album')
            ->leftJoin(Entity\Artist::class, 'artist', 'WITH', 'artist.id = 0');
        $expectedDQL  = 'SELECT album, artist FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';
        $expectedDQL .= ' LEFT JOIN Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Artist artist';

        $expectedDQL .= ' WITH artist.id = 0';
        self::assertSame($expectedDQL, $qb->getQuery()->getDQL());
        $expectedSQL  = 'SELECT a0_.id AS ID_0, a0_.timeCreated AS TIMECREATED_1, a0_.name AS NAME_2, a1_.id AS ID_3,';
        $expectedSQL .= ' a1_.name AS NAME_4, a0_.artist_id AS ARTIST_ID_5, a1_.type_id AS TYPE_ID_6 FROM ALBUM a0_';
        $expectedSQL .= ' LEFT JOIN ARTIST a1_ ON (a1_.id = 0)';
        self::assertSame($expectedSQL, $qb->getQuery()->getSQL());
        $results = $qb->getQuery()->getResult();
        self::assertIsArray($results);
        self::assertArrayHasKey(0, $results);
        self::assertInstanceOf(Entity\Album::class, $results[0]);

        self::assertSame(1, $results[0]->getId());
        self::assertInstanceOf(Entity\Artist::class, $results[0]->getArtist());

        self::assertSame(2, $results[0]->getArtist()->getId());
        self::assertNull($results[1]);
    }

    public function testSelectWithWhere(): void
    {
        $qb = $this->_entityManager->createQueryBuilder();
        $qb
            ->select('album')
            ->from(Entity\Album::class, 'album')
            ->where('album.id > 1');
        $expectedDQL = 'SELECT album FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';

        $expectedDQL .= ' WHERE album.id > 1';
        self::assertSame($expectedDQL, $qb->getQuery()->getDQL());
        $expectedSQL  = 'SELECT a0_.id AS ID_0, a0_.timeCreated AS TIMECREATED_1, a0_.name AS NAME_2,';
        $expectedSQL .= ' a0_.artist_id AS ARTIST_ID_3 FROM ALBUM a0_ WHERE a0_.id > 1';
        self::assertSame($expectedSQL, $qb->getQuery()->getSQL());
        $albums = $qb->getQuery()->getResult();
        self::assertIsArray($albums);
        self::assertArrayHasKey(0, $albums);
        self::assertInstanceOf(Entity\Album::class, $albums[0]);

        self::assertSame(2, $albums[0]->getId());
    }

    public function testSelectWithLimit(): void
    {
        $qb = $this->_entityManager->createQueryBuilder();
        $qb
            ->select('album')
            ->from(Entity\Album::class, 'album')
            ->where('album.id > 0')
            ->setMaxResults(1);
        $expectedDQL = 'SELECT album FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';

        $expectedDQL .= ' WHERE album.id > 0';
        self::assertSame($expectedDQL, $qb->getQuery()->getDQL());
        $expectedSQL  = 'SELECT a0_.id AS ID_0, a0_.timeCreated AS TIMECREATED_1, a0_.name AS NAME_2,';
        $expectedSQL .= ' a0_.artist_id AS ARTIST_ID_3 FROM ALBUM a0_ WHERE a0_.id > 0 ROWS 1 TO 1';
        self::assertSame($expectedSQL, $qb->getQuery()->getSQL());
        $albums = $qb->getQuery()->getResult();
        self::assertIsArray($albums);
        self::assertCount(1, $albums);
        self::assertArrayHasKey(0, $albums);
        self::assertInstanceOf(Entity\Album::class, $albums[0]);

        self::assertSame(1, $albums[0]->getId());
    }

    public function testSelectWithOffset(): void
    {
        $qb = $this->_entityManager->createQueryBuilder();
        $qb
            ->select('album')
            ->from(Entity\Album::class, 'album')
            ->where('album.id > 0')
            ->setFirstResult(1);
        $expectedDQL = 'SELECT album FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';

        $expectedDQL .= ' WHERE album.id > 0';
        self::assertSame($expectedDQL, $qb->getQuery()->getDQL());
        $expectedSQL  = 'SELECT a0_.id AS ID_0, a0_.timeCreated AS TIMECREATED_1, a0_.name AS NAME_2,';
        $expectedSQL .= ' a0_.artist_id AS ARTIST_ID_3 FROM ALBUM a0_ WHERE a0_.id > 0 ROWS 2 TO ' . PHP_INT_MAX;
        self::assertSame($expectedSQL, $qb->getQuery()->getSQL());
        $albums = $qb->getQuery()->getResult();
        self::assertIsArray($albums);
        self::assertArrayHasKey(0, $albums);
        self::assertInstanceOf(Entity\Album::class, $albums[0]);

        self::assertSame(2, $albums[0]->getId());
    }

    public function testSelectWithOffsetAndLimit(): void
    {
        $qb = $this->_entityManager->createQueryBuilder();
        $qb
            ->select('album')
            ->from(Entity\Album::class, 'album')
            ->where('album.id > 0')
            ->setFirstResult(1)
            ->setMaxResults(1);
        $expectedDQL = 'SELECT album FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';

        $expectedDQL .= ' WHERE album.id > 0';
        self::assertSame($expectedDQL, $qb->getQuery()->getDQL());
        $expectedSQL  = 'SELECT a0_.id AS ID_0, a0_.timeCreated AS TIMECREATED_1, a0_.name AS NAME_2,';
        $expectedSQL .= ' a0_.artist_id AS ARTIST_ID_3 FROM ALBUM a0_ WHERE a0_.id > 0 ROWS 2 TO 2';
        self::assertSame($expectedSQL, $qb->getQuery()->getSQL());
        $albums = $qb->getQuery()->getResult();
        self::assertIsArray($albums);
        self::assertCount(1, $albums);
        self::assertArrayHasKey(0, $albums);
        self::assertInstanceOf(Entity\Album::class, $albums[0]);

        self::assertSame(2, $albums[0]->getId());
    }

    public function testSelectWithWhereAndParameters(): void
    {
        $qb = $this->_entityManager->createQueryBuilder();
        $qb
            ->select('album')
            ->from(Entity\Album::class, 'album')
            ->where('album.id = :id')
            ->setParameter('id', 1);
        $expectedDQL = 'SELECT album FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';

        $expectedDQL .= ' WHERE album.id = :id';
        self::assertSame($expectedDQL, $qb->getQuery()->getDQL());
        $expectedSQL  = 'SELECT a0_.id AS ID_0, a0_.timeCreated AS TIMECREATED_1, a0_.name AS NAME_2,';
        $expectedSQL .= ' a0_.artist_id AS ARTIST_ID_3 FROM ALBUM a0_ WHERE a0_.id = ?';
        self::assertSame($expectedSQL, $qb->getQuery()->getSQL());
        $albums = $qb->getQuery()->getResult();
        self::assertIsArray($albums);
        self::assertArrayHasKey(0, $albums);
        self::assertInstanceOf(Entity\Album::class, $albums[0]);

        self::assertSame(1, $albums[0]->getId());
    }

    public function testSelectWithGroupBy(): void
    {
        $qb = $this->_entityManager->createQueryBuilder();
        $qb
            ->select('album.id')
            ->from(Entity\Album::class, 'album')
            ->join('album.artist', 'artist')
            ->groupBy('album.id');
        $expectedDQL = 'SELECT album.id FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';

        $expectedDQL .= ' INNER JOIN album.artist artist GROUP BY album.id';
        self::assertSame($expectedDQL, $qb->getQuery()->getDQL());
        $expectedSQL = 'SELECT a0_.id AS ID_0 FROM ALBUM a0_ INNER JOIN ARTIST a1_ ON a0_.artist_id = a1_.id GROUP BY a0_.id';
        self::assertSame($expectedSQL, $qb->getQuery()->getSQL());
        $albumIds = $qb->getQuery()->getResult();
        self::assertIsArray($albumIds);
        self::assertCount(2, $albumIds);
        self::assertArrayHasKey(0, $albumIds);
        self::assertArrayHasKey('id', $albumIds[0]);
        self::assertSame(1, $albumIds[0]['id']);
    }

    public function testSelectWithHaving(): void
    {
        $qb = $this->_entityManager->createQueryBuilder();
        $qb
            ->select('album.id')
            ->from(Entity\Album::class, 'album')
            ->join('album.artist', 'artist')
            ->groupBy('album.id')
            ->having('album.id > 1');
        $expectedDQL = 'SELECT album.id FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';

        $expectedDQL .= ' INNER JOIN album.artist artist GROUP BY album.id HAVING album.id > 1';
        self::assertSame($expectedDQL, $qb->getQuery()->getDQL());
        $expectedSQL  = 'SELECT a0_.id AS ID_0 FROM ALBUM a0_ INNER JOIN ARTIST a1_ ON a0_.artist_id = a1_.id';
        $expectedSQL .= ' GROUP BY a0_.id HAVING a0_.id > 1';
        self::assertSame($expectedSQL, $qb->getQuery()->getSQL());
        $albumIds = $qb->getQuery()->getResult();
        self::assertIsArray($albumIds);
        self::assertCount(1, $albumIds);
        self::assertArrayHasKey(0, $albumIds);
        self::assertArrayHasKey('id', $albumIds[0]);
        self::assertSame(2, $albumIds[0]['id']);
    }

    public function testSelectWithOrderBy(): void
    {
        $qb = $this->_entityManager->createQueryBuilder();
        $qb
            ->select('album')
            ->from(Entity\Album::class, 'album')
            ->orderBy('album.id', 'DESC');
        $expectedDQL = 'SELECT album FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';

        $expectedDQL .= ' ORDER BY album.id DESC';
        self::assertSame($expectedDQL, $qb->getQuery()->getDQL());
        $expectedSQL  = 'SELECT a0_.id AS ID_0, a0_.timeCreated AS TIMECREATED_1, a0_.name AS NAME_2,';
        $expectedSQL .= ' a0_.artist_id AS ARTIST_ID_3 FROM ALBUM a0_ ORDER BY a0_.id DESC';
        self::assertSame($expectedSQL, $qb->getQuery()->getSQL());
        $albums = $qb->getQuery()->getResult();
        self::assertIsArray($albums);
        self::assertArrayHasKey(0, $albums);
        self::assertInstanceOf(Entity\Album::class, $albums[0]);

        self::assertNotSame(1, $albums[0]->getId());
    }

    public function testSelectWithSubselect(): void
    {
        $qb1 = $this->_entityManager->createQueryBuilder();
        $qb2 = $this->_entityManager->createQueryBuilder();
        $qb1
            ->select('artist')
            ->from(Entity\Artist::class, 'artist')
            ->where('artist.id = 2');
        $qb2
            ->select('album')
            ->from(Entity\Album::class, 'album')
            ->where($qb2->expr()->in('album.artist', $qb1->getDQL()));
        $expectedDQL = 'SELECT album FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Album album';

        $expectedDQL .= ' WHERE album.artist IN(SELECT artist';
        $expectedDQL .= ' FROM Satag\\DoctrineFirebirdDriver\\Test\\Resource\\Entity\\Artist artist WHERE artist.id = 2)';

        self::assertSame($expectedDQL, $qb2->getQuery()->getDQL());
        $expectedSQL = 'SELECT a0_.id AS ID_0, a0_.timeCreated AS TIMECREATED_1, a0_.name AS NAME_2, a0_.artist_id AS ARTIST_ID_3 FROM ALBUM a0_ WHERE a0_.artist_id IN (SELECT a1_.id FROM ARTIST a1_ WHERE a1_.id = 2)';
        self::assertSame($expectedSQL, $qb2->getQuery()->getSQL());
        $albums = $qb2->getQuery()->getResult();
        self::assertIsArray($albums);
        self::assertCount(1, $albums);
        self::assertArrayHasKey(0, $albums);
        self::assertInstanceOf(Entity\Album::class, $albums[0]);

        self::assertSame(1, $albums[0]->getId());
    }
}
