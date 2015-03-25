<?php
/**
 * Created by IntelliJ IDEA.
 * User: JakubM
 * Date: 04/11/14
 * Time: 13:41
 */

namespace Keboola\AdWordsExtractor\Controller;


use Keboola\Syrup\Exception\ApplicationException;
use Keboola\Syrup\Elasticsearch\JobMapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


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

		// check params against ES mapping
		$this->checkMappingParams($params);

		// Create new job
		$job = $this->createJob('run', $params);

		// Add job to Elasticsearch
		try {
			/** @var JobMapper $jobMapper */
			$jobMapper = $this->container->get('syrup.elasticsearch.current_component_job_mapper');
			$jobId = $jobMapper->create($job);
		} catch (\Exception $e) {
			throw new ApplicationException("Failed to create job", $e);
		}

		$queueName = 'ex-adwords';
		$queueParams = $this->container->getParameter('queue');

		if (isset($queueParams['sqs'])) {
			$queueName = $queueParams['sqs'];
		}
		$messageId = $this->enqueue($jobId, $queueName);

		$this->logger->info('Job created', [
			'sqsQueue' => $queueName,
			'sqsMessageId' => $messageId,
			'job' => $job->getLogData()
		]);

		// Response with link to job resource
		return $this->createJsonResponse([
			'id'        => $jobId,
			'url'       => $this->getJobUrl($jobId),
			'status'    => $job->getStatus()
		], 202);
	}

}
