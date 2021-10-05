<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WebHookController extends AbstractController
{
    /**
     * @Route("/webhook", name="web_hook")
     */
    public function index(Request $request): Response
    {
        \Stripe\Stripe::setApiKey('sk_test_VePHdqKTYQjKNInc7u56JBrQ');
        //$payload = @file_get_contents('php://input');
        $payload = $request->getContent();
        
        $event = null;
        try {
            $event = \Stripe\Event::constructFrom(
            json_decode($payload, true)
        );
        } catch(\UnexpectedValueException $e) {
            // Invalid payload
            echo '⚠️  Webhook error while parsing basic request.';
            http_response_code(400);
            exit();
        }

        // Handle the event
        switch ($event->type) {
        case 'invoice.created':
            $invoice = $event->data->object;
        case 'invoice.deleted':
            $invoice = $event->data->object;
        case 'invoice.finalization_failed':
            $invoice = $event->data->object;
        case 'invoice.finalized':
            $invoice = $event->data->object;
        case 'invoice.marked_uncollectible':
            $invoice = $event->data->object;
        case 'invoice.paid':
            $invoice = $event->data->object;
        case 'invoice.payment_action_required':
            $invoice = $event->data->object;
        case 'invoice.payment_failed':
            $invoice = $event->data->object;
        case 'invoice.payment_succeeded':
            $invoice = $event->data->object;
        case 'invoice.sent':
            $invoice = $event->data->object;
        case 'invoice.upcoming':
            $invoice = $event->data->object;
        case 'invoice.updated':
            $invoice = $event->data->object;
        case 'invoice.voided':
            $invoice = $event->data->object;
        // ... handle other event types
        default:
            echo 'Received unknown event type ' . $event->type;
        }
        
        http_response_code(200);
        
        $data =  ['invoice' => $invoice];
        $response = $this->json($data, 200, [], ['groups'=>'conversation:read']);

        return $response;
        /* return $this->render('web_hook/index.html.twig', [
            'controller_name' => 'WebHookController',
        ]); */
    }
}