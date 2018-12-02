<?php

namespace AppBundle\Services;


use AppBundle\Entity\Student;
use AppBundle\Entity\Request_student;
use AppBundle\Repository\StudentRepository;
use Doctrine\ORM\EntityManager;

class RequestStudentHelper
{
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function getRequest($id)
    {
        /** @var Request_student $requestStudent*/
        $requestStudent = $this->em->getRepository("AppBundle:Request_student");
        return $requestStudent->find($id);
    }

    public function getAllRequestStudents()
    {
        /** @var Request_studentRepository $studentsRepository */
        $studentsRepository = $this->em->getRepository("AppBundle:Request_student");

        return $studentsRepository->getAllStudents();
    }
}