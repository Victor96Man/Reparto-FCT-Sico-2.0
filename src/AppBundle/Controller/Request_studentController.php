<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Response;

use AppBundle\Entity\Student;
use AppBundle\Entity\Project;
use AppBundle\Entity\School_group;
use AppBundle\Entity\Request_student;
use AppBundle\Services\ConvocatoriesHelper;
use AppBundle\Services\StudentsHelper;
use AppBundle\Services\SchoolGroupsHelper;
use AppBundle\Services\CyclesHelper;
use AppBundle\Form\ProjectType;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use PHPExcel_IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class Request_studentController extends Controller
{
    /**
     * @Route("/user/student/requestStudent/new", name="user_student_reqcom")
     * @Method({"GET", "POST"})
     */
    public function newRequestStudentAction(Request $request)
    {
        $current_convocatory = $this->getUser()->getCurrentConvocatory();
        if(!$this->get('app.functionsHelper')->isConvocatoryValid($current_convocatory)) {
            $request->getSession()
                ->getFlashBag()
                ->add('error', 'Convocatoria antigua (Solo lectura)')
            ;
            return $this->redirectToRoute('panel_students');
        }

        $groups = array();

        /** @var CyclesHelper $cyclesHelper */
        $cyclesHelper = $this->get('app.cyclesHelper');

        /** @var SchoolGroupsHelper $schoolGroupsHelper */
        $schoolGroupsHelper = $this->get('app.schoolGroupsHelper');

        /** @var School_group $group */
        foreach ($schoolGroupsHelper->getGroupsCourse(2) as $group) {
            $groups[$group->__toString()] = $group;
        }

        /** @var SchoolGroupsHelper $groupHelper */
        $groupHelper = $this->get('app.schoolGroupsHelper');

        /** @var CyclesHelper $cyclesHelper */
        $cyclesHelper = $this->get('app.cyclesHelper');

        $btn_submit = $request->get('btn_submit');
        $file = $request->files->get('file_uploaded');

        $error = "";

        if(isset($btn_submit)) {
            if(isset($file)) {
                $routeDirectory = '../web/uploads/assets';
                $file = $request->files->get('file_uploaded');
            
                $cycle =  $request->request->get('selectGroupDistribution');
                $fileName = md5(uniqid()) . '.' . $file->guessExtension();
                $original_name = $file->getClientOriginalName();
                $file->move($routeDirectory, $fileName);
                $path = $routeDirectory . '/' . $fileName;

                $file_entity = new UploadedFile ($path, $original_name);

                $objReader = PHPExcel_IOFactory::createReader('Excel2007');
                $objReader->setLoadSheetsOnly('Students'); // Especificamos la hoja de cálculo
                $objPHPExcel = $objReader->canRead($file_entity);

                if ($objPHPExcel) { //El archivo es válido
                    $objPHPExcel = $objReader->load($file_entity);

                    $worksheet = $objPHPExcel->getActiveSheet();
                    $cells = $worksheet->toArray();

                    $entityManager = $this->getDoctrine()->getManager();
                    $emCov = $entityManager->getRepository('AppBundle:Convocatory');
                    $currentConvocatory = $this->getUser()->getCurrentConvocatory();

                    for ($i = 1; $i < $worksheet->getHighestRow(); $i++) {
                        if ($cells[$i][0] == null) {
                            break;
                        }
                        try{
                            $entityManager = $this->getDoctrine()->getManager();
                            $emCov = $entityManager->getRepository('AppBundle:Convocatory');
                            $currentConvocatory = $this->getUser()->getCurrentConvocatory();
                            
                            $request_student = new Request_student();

                            $groups = array();
                            $convocatories = array();

                            /** @var SchoolGroupsHelper $schoolGroupsHelper */
                            $schoolGroupsHelper = $this->get('app.schoolGroupsHelper');

                            /** @var ConvocatoriesHelper $convocatoriesHelper */
                            $convocatoriesHelper = $this->get('app.convocatoriesHelper');

                            /** @var School_group $group */
                            foreach ($schoolGroupsHelper->getGroupsCourse(2) as $group) {
                                if($group->getId() == $cycle) {
                                    $groups[$group->__toString()] = $group;
                                    $request_student->setGroupId($groups[$group->__toString()]);
                                }
                                
                            }

                            /** @var Convocatory $convocatory */
                            foreach ($convocatoriesHelper->getAllConvocatories() as $convocatory) {
                                if($currentConvocatory == $convocatory->getId()) {
                                    $convocatories[$convocatory->__toString()] = $convocatory;
                                    $request_student->setConvocatory($convocatories[$convocatory->__toString()]);
                                }   
                            }
                            
                            $nombre = explode(',', $cells[$i][0]);
                            $request_student->setFirstName($nombre[1]);
                            $request_student->setLastName($nombre[0]);
                            $request_student->setPiExento(0);
                            $request_student->setFctExento(0);
                            
                            $entityManager->persist($request_student);
                            $entityManager->flush();
                        }catch (DBALException $e){
                            $error = 'Ha sucedido un problema en la fila ('. $i .') del documento.';
                            break;
                        }
                    }

                    if($error == "") {
                        $request->getSession()
                            ->getFlashBag()
                            ->add('success', 'Solicitudes creadas');
                        $fileSystem = new Filesystem();
                        $fileSystem->remove($path);
                        return $this->redirectToRoute('panel_students', ['_fragment' => 'solemp']);
                    }

                } else {//Si el archivo no es válido
                    $error = "El archivo no es válido";
                }
                $fileSystem = new Filesystem();
                $fileSystem->remove($path);
            }else{
                $error = "No has subido ningún archivo";
            }
        }
        return $this->render('user/student/request_student/new.html.twig', array(
            'title' => "Importar datos de alumnos",
            'error' => $error,
            'cycles' => $cyclesHelper->getCycles(), 
            'groups' => $groupHelper->getGroupsCourse(2)
       
        ));
    }

    /**
     * @Route("user/student/request_student/{id}/show", name="user_student_show_request_student", methods="GET")
     */
    public function showProject(Request_student $request_student)
    {
        $em = $this->getDoctrine();

        return $this->render('user/student/request_student/show.html.twig', array(
            'request_student' => $request_student,
        ));
    }


    /**
     * @Route("/user/student/requestStudent/massiveSelect", name="user_student_massive_request_student")
     */
    public function massiveAction(Request $request)
    {
        $current_convocatory = $this->getUser()->getCurrentConvocatory();
        if(!$this->get('app.functionsHelper')->isConvocatoryValid($current_convocatory)) {
            $request->getSession()
                ->getFlashBag()
                ->add('error', 'Convocatoria antigua (Solo lectura)')
            ;
            return $this->redirectToRoute('panel_students');
        }

        $type = 'success';
        $msg = "";
        $numerrors = 0;

        $btnSave = $request->get('savemassive');
        $btnDelete = $request->get('deletemassive');

        if(isset($btnSave)){ // Pulsado botón guardar
            $solicitudes = $request->get('solc');

            if(!$solicitudes){
                $type = 'error';
                $msg = 'Solicitudes no seleccionadas';
            }else{
                $em = $this->getDoctrine()->getManager();
                foreach ($solicitudes as $key => $value){
                    $request_student = $this->get('app.requestStudent')->getRequest($key);
                    
                    $newStudent = new Student();
                    $newStudent->setGroupId($request_student->getGroupId());
                    $newStudent->setConvocatory($request_student->getConvocatory());
                    $newStudent->setFirstName($request_student->getFirstName());
                    $newStudent->setLastName($request_student->getLastName());
                    $newStudent->setPiExento($request_student->getPiExento());
                    $newStudent->setFctExento($request_student->getFctExento());

                    try{
                        $validator = $this->get('validator');
                        $errorCode = $validator->validate($newStudent)->getIterator();
                        if(!isset($errorCode[0]) || $errorCode[0]->getCode() != '23bd9dbf-6b9b-41cd-a99e-4844bcf3077f'){
                            $em->persist($newStudent);
                            $em->flush();

                            $em->remove($request_student);
                            $em->flush();

                        }else{
                            $msg = 'Ya existe '.$newStudent->getName();
                            $request->getSession()
                                ->getFlashBag()
                                ->add('error', $msg)
                            ;
                            $numerrors++;
                        }

                    }catch (DBALException $e){
                        $numerrors++;
                        $msg = "Error inesperado";
                        $request->getSession()
                            ->getFlashBag()
                            ->add('error', $msg)
                        ;
                        break;
                    }

                }
                if($numerrors > 0 && $numerrors == count($solicitudes)){ // No se ha creado ninguna empresa
                    $type = 'error';
                    $msg = 'No se ha creado ningun alumno';
                }else{
                    $type = 'success';
                    $msg = 'Se ha creado '. (count($solicitudes) - $numerrors) .' de '. count($solicitudes);
                }
            }

        }else if(isset($btnDelete)){ // Pulsado botón borrar
            $solicitudes = $request->get('solc');

            if(!$solicitudes){
                $type = 'error';
                $msg = 'Alumnos no seleccionados';
            }else{
                foreach ($solicitudes as $key => $value){
                    $request_student = $this->get('app.requestStudent')->getRequest($key);

                    try{
                        $em = $this->getDoctrine()->getManager();
                        $em->remove($request_student);
                        $em->flush();
                        $msg = 'Datos de importación borrados';
                    }catch (DBALException $e){
                        $numerrors++;
                        switch ($e->getPrevious()->errorInfo[1]){
                            case 1451:
                                $msg = "La solicitud se está usando";
                                break;
                            default:
                                $msg = "Error inesperado";
                                break;
                        }

                        $request->getSession()
                            ->getFlashBag()
                            ->add('error', $msg)
                        ;
                        break;
                    }

                }
                if($numerrors > 0){
                    $type = 'error';
                    $msg = 'Ha ocurrido '.$numerrors.' error/es';
                }
            }

        }else{
            $type = 'error';
            $msg = 'No has pulsado ningún botón';
        }

        $request->getSession()
            ->getFlashBag()
            ->add($type, $msg)
        ;

        return $this->redirectToRoute('panel_students', ['_fragment' => 'solemp']);
    }
}