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
            ->leftJoin('n.status', 'status')
            ->innerJoin('n.toApplication', 'to_app', Expr\Join::WITH,
                $qb->expr()->andX(
                    $qb->expr()->eq('to_app.isActive', true), # -- active applications
                    $qb->expr()->eq('to_app.isDefault', $qb->expr()->literal(false)), # -- exclude default application
                    $qb->expr()->isNotNull('to_app.requestKey'), # -- request key should not be null
                    $qb->expr()->neq('to_app.id', 'from_app.id')
                )
            )
            ->andWhere($qb->expr()->in('n.syncStatus', array('pending', 'error')),
                $qb->expr()->notIn('status', array('cancelled', 'expired')));

        return $qb->getQuery()->getResult();
    }

    /**
     * notification with pending or expired states
     * only from the default app
     * @return array
     */
    public function getStateUpdateNotifications()
    {
        $dateNow = new \DateTime();
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('notification')
            ->from('NotificationBundle:Notification', 'notification')
            ->leftJoin('notification.status', 'nSts')
            ->andWhere(
                $qb->expr()->in('nSts.code', array('available', 'scheduled')),# -- notification status
                $qb->expr()->orX(
                    $qb->expr()->lte('notification.scheduleDate', ':dateNow'),# -- notifications with the status scheduled for the day
                    $qb->expr()->lte('notification.expirationDate', ':dateNow')# -- Notifications with the status available expired
                )
            )
            ->setParameter('dateNow', $dateNow);
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
                $qb->expr()->in('n_sts.code', array('scheduled', 'available'))
            );
        if ($destination)
            $qb->andWhere($qbs->expr()->notIn('n.id', $qbs->getDQL()));

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array $params
     * @return array
     */
    public function getAllWithPaginationSupport($params = array())
    {
        # -- handling user specific applications behaviors
        $excludeApplicationsFilters = array_key_exists('exclude_applications_filters', $params) ? true : false;
        $fromDefatulApplication = array_key_exists('from_defatul_application', $params) ? true : false;
        $fromApplication = array_key_exists('from_application', $params) ? true : false;
        $fromApplicationId = array_key_exists('from_application_id', $params) ? $params['from_application_id'] : '';
        $toDefatulApplication = array_key_exists('to_defatul_application', $params) ? true : false;
        $toApplication = array_key_exists('to_application', $params) ? true : false;
        $toApplicationId = array_key_exists('to_application_id', $params) ? $params['to_application_id'] : '';

        # -- column filters
        $filters = array_key_exists('column_filters', $params) ? $params['column_filters'] : array();
        # -- limit & offset
        $limit = array_key_exists('limit', $params) ? intval($params['limit']) : 15;
        $offset = array_key_exists('offset', $params) ? intval($params['offset']) : 0;
        # -- sort & order
        $sortBy = array_key_exists('sortBy', $params) ? $params['sortBy'] : 'n.scheduleDate';
        $orderBy = array_key_exists('orderBy', $params) ? $params['orderBy'] : 'desc';

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('n')
            ->from('NotificationBundle:Notification', 'n')
            ->leftJoin('n.status', 'status')
            ->leftJoin('n.type', 'type')
            ->leftJoin('n.fromApplication', 'fromApplication')
            ->leftJoin('n.toApplication', 'toApplication');

        $qb->andWhere($qb->expr()->notIn('status.code', array('cancelled')));

        # -- not application filters options goes here


        # -- column filters goes here
        # columns filters
        foreach ($filters as $field => $value) {
            if ($field == "" || $value == "") continue;
            $dateNow = new \DateTime();
            // Manage relationships
            switch ($field) {
                case "n.subject":
                    $qb->andWhere($qb->expr()->like("n.subject", $qb->expr()->literal("%" . $value . "%")));
                    break;
                case "statu.name":
                    $qb->andWhere($qb->expr()->like("status.name", $qb->expr()->literal("%" . $value . "%")));
                    break;
                case "status.code":
                    $qb->andWhere($qb->expr()->eq("status.code", $qb->expr()->literal($value)));
                    break;
                case "type.name":
                    $qb->andWhere($qb->expr()->like("type.name", $qb->expr()->literal("%" . $value . "%")));
                    break;
                case "type.code":
                    $qb->andWhere($qb->expr()->eq("type.code", $qb->expr()->literal($value)));
                    break;
                case "n.scheduleDate":
                        $start_date = \DateTime::createFromFormat("m/d/Y H:i:s", $value . "00:00:00")->format("Y-m-d H:i:s");
                        $end_date = \DateTime::createFromFormat("m/d/Y H:i:s", $value . "23:59:59")->format("Y-m-d H:i:s");
                        $qb->andWhere(
                            $qb->expr()->gte("n.scheduleDate", $qb->expr()->literal($start_date)),
                            $qb->expr()->lte("n.scheduleDate", $qb->expr()->literal($end_date)));
                    break;
                case "n.expirationDate":
                        $start_date = \DateTime::createFromFormat("m/d/Y H:i:s", $value . "00:00:00")->format("Y-m-d H:i:s");
                        $end_date = \DateTime::createFromFormat("m/d/Y H:i:s", $value . "23:59:59")->format("Y-m-d H:i:s");
                        $qb->andWhere(
                            $qb->expr()->gte("n.expirationDate", $qb->expr()->literal($start_date)),
                            $qb->expr()->lte("n.expirationDate", $qb->expr()->literal($end_date))
                        );
                        break;
            }
        }

        # -- total records
        $totalQb = clone $qb;
        $total = $totalQb->select('COUNT(n)')->getQuery()->getSingleScalarResult();

        # -- sort and order
        $qb->addOrderBy($sortBy, $orderBy);

        # -- limit and sort goes here
        $qb->setMaxResults($limit);
        $qb->setFirstResult(($offset * $limit));

        $notifications = $qb->getQuery()->getResult();

        return array(
            'notifications' => $notifications,
            'totalRecords' => intval($total),
            'pages' => intval(ceil(intval($total) / $limit)),
        );

    }

}
