<?php
namespace Zeega\ApiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

use Zeega\DataBundle\Entity\Item;
use Zeega\CoreBundle\Helpers\ItemCustomNormalizer;
use Zeega\CoreBundle\Helpers\ResponseHelper;

class ProjectsController extends Controller
{
    //  get_collections GET    /api/collections.{_format}
    public function getProjectAction($id)
    {	
	
		$user = $this->get('security.context')->getToken()->getUser();

		$sequences = $this->getDoctrine()
						  ->getRepository('ZeegaDataBundle:Sequence')
						  ->findSequencesByProject($id);

		$project = $this->getDoctrine()
						->getRepository('ZeegaDataBundle:Project')
						->findOneById($id);
		
		$frames = array();
		$layers = array();
		// auch - should work for now, but won't scale for sure
		foreach($sequences as $sequence)
		{
			$sequenceId = $sequence->getId();
			$frames[$sequenceId] = $this->getDoctrine()
									    ->getRepository('ZeegaDataBundle:Sequence')
										->findFramesBySequenceId($project->getId());
			
			$sequence = $this->getDoctrine()
						     ->getRepository('ZeegaDataBundle:Sequence')
							 ->find($sequence->getId());

			$layers[$sequenceId] = array();			
			$layers_seq = $sequence->getLayers()->toArray();
			foreach($layers_seq as $layer)
			{
				$l = $this->getDoctrine()
					      ->getRepository('ZeegaDataBundle:Layer')
						  ->findLayerById($layer->getId());

				array_push($layers[$sequenceId], $l); 
			}
		}
		
		$projectView = $this->renderView('ZeegaApiBundle:Projects:show.json.twig', array('project' => $project, 
			'sequences' => $sequences, 'frames' => $frames, 'layers' => $layers));
		
    	return ResponseHelper::compressTwigAndGetJsonResponse($projectView);
    } 

    //  get_collections GET    /api/collections.{_format}
    public function getProjectsAction()
    {
    } 
}
