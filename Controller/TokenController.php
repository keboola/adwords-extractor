<?php

namespace Keboola\AdWordsExtractorBundle\Controller;

use Keboola\AdWordsExtractorBundle\AdWords\Api;
use Keboola\AdWordsExtractorBundle\AdWords\AppConfiguration;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\NotBlank;

class TokenController extends Controller
{
	public function tokenAction(Request $request)
	{
		$session = new Session();
		$session->start();

		$form = $this->createFormBuilder(array())
			->add('token', 'text', array(
				'label' => 'Developer Token',
				'constraints' => new NotBlank()
			))
			->add('send', 'submit', array(
				'label' => 'Get Refresh Token'
			))
			->getForm();
		$form->handleRequest($request);

		if ($form->isValid()) {
			$data = $form->getData();
			/** @var AppConfiguration $appConfiguration */
			$appConfiguration = $this->container->get('ex_adwords.app_configuration');
			/** @var \Symfony\Component\Routing\RequestContext $context */
			$context = $this->container->get('router')->getContext();

			$session->set('developer_token', $data['token']);
			return $this->redirect(Api::getOAuthUrl($appConfiguration->client_id, $appConfiguration->client_secret, $data['token'],
				sprintf('https://%s%s/ex-adwords/token-callback', $context->getHost(), $context->getBaseUrl())));
		}

		return $this->render('KeboolaAdWordsExtractorBundle:Token:input.html.twig', array('form' => $form->createView()));
	}

	public function tokenCallbackAction(Request $request)
	{
		$session = new Session();
		$session->start();
		$developerToken = $session->get('developer_token');

		$params = $request->query->all();
		if (!isset($params['code']) || !$developerToken) {
			$session->getFlashBag()->add('warning', 'Unknown error occurred, try again please');
			return $this->redirect('/ex-adwords/token');
		}

		/** @var AppConfiguration $appConfiguration */
		$appConfiguration = $this->container->get('ex_adwords.app_configuration');
		/** @var \Symfony\Component\Routing\RequestContext $context */
		$context = $this->container->get('router')->getContext();

		$refreshToken = Api::getRefreshToken($appConfiguration->client_id, $appConfiguration->client_secret, $developerToken,
			$params['code'], sprintf('https://%s%s/ex-adwords/token-callback', $context->getHost(), $context->getBaseUrl()));

		if (!$refreshToken) {
			$session->getFlashBag()->add('warning', 'Unknown error occurred, try again please');
			return $this->redirect('/ex-adwords/token');
		}

		return $this->render('KeboolaAdWordsExtractorBundle:Token:result.html.twig', array(
			'developer_token' => $developerToken,
			'refresh_token' => $refreshToken
		));
	}
}
