<?php
/**
 * Created by IntelliJ IDEA.
 * User: JakubM
 * Date: 04/11/14
 * Time: 13:41
 */

namespace Keboola\AdWordsExtractor\Controller;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Keboola\Syrup\Job\Metadata\Job;

class ApiController extends \Keboola\Syrup\Controller\ApiController
{

	/**
	 * Run Action
	 *
	 * Creates new job, saves it to Elasticsearch and add to SQS
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function runAction(Request $request)
	{
		// Get params from request
		$params = $this->getPostJson($request);

		// Create new job
		/** @var Job $job */
		$job = $this->createJob('run', $params);

		// Add job to Elasticsearch
		$jobId = $this->getJobManager()->indexJob($job);

		// Add job to SQS
		$this->enqueue($jobId, 'ex-adwords');

		// Response with link to job resource
		return $this->createJsonResponse([
			'id'        => $jobId,
			'url'       => $this->getJobUrl($jobId),
			'status'    => $job->getStatus()
		], 202);
	}

}