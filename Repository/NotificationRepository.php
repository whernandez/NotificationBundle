<?php

namespace NTI\NotificationBundle\Repository;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;
use NTI\NotificationBundle\Entity\Destination;

/**
 * NotificationRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class NotificationRepository extends EntityRepository
{

    /**
     * external notifications from actives application
     * different from the default application.
     * @return array
     */
    public function getExternalNotifications()
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('n')
            ->from('NotificationBundle:Notification', 'n')
            ->leftJoin('n.fromApplication', 'from_app')
            ->innerJoin('n.toApplication', 'to_app', Expr\Join::WITH,
                $qb->expr()->andX(
                    $qb->expr()->eq( 'to_app.isActive', true), # -- active applications
                    $qb->expr()->eq( 'to_app.isDefault', $qb->expr()->literal(false)), # -- exclude default application
                    $qb->expr()->isNotNull( 'to_app.requestKey'), # -- request key should not be null
                    $qb->expr()->neq('to_app.id', 'from_app.id')
                )
            )
        ->andWhere($qb->expr()->in('n.syncStatus',array('pending','error')));

        return $qb->getQuery()->getResult();
    }

    /**
     * return the list of notifications with all destinations boolean property active where the
     * given destinationId is not included.
     *
     * @param string $destinationId
     * @return array
     */
    public function getByAllDestinationActive(Destination $destination = null)
    {
        $qbs = $this->getEntityManager()->createQueryBuilder();
        if ($destination) {
            $qbs->select('d_noti.id')
                ->from('NotificationBundle:Destination', 'd')
                ->innerJoin('d.notification', 'd_noti')
                ->andWhere(
                    $qbs->expr()->eq('d_noti.allDestinations', $qbs->expr()->literal(true)),
                    $qbs->expr()->eq('d.destinationId', $qbs->expr()->literal($destination->getDestinationId()))
                );
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('n')
            ->from('NotificationBundle:Notification', 'n')
            ->leftJoin('n.status', 'n_sts')
            ->andWhere(
                $qb->expr()->eq('n.allDestinations', $qb->expr()->literal(true)),
                $qb->expr()->in('n_sts.code', array('scheduled','available'))
            );
        if ($destination)
            $qb->andWhere($qbs->expr()->notIn('n.id', $qbs->getDQL()));

        return $qb->getQuery()->getResult();
    }

}
