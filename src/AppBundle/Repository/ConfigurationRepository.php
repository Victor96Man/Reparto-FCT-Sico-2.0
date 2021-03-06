<?php

namespace AppBundle\Repository;

/**
 * ConfigurationRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ConfigurationRepository extends \Doctrine\ORM\EntityRepository
{
    public function getConfig(){
        return $this->find(1);
    }

    public function getOrganizationName()
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('t.organization_name')
            ->from('AppBundle:Configuration', 't');

        return $qb->getQuery()->getArrayResult()[0]['organization_name'];
    }
}
