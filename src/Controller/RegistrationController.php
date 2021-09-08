<?php

namespace App\Controller;

use App\Entity\Abonnement;
use App\Entity\Client;
use App\Entity\Facture;
use App\Entity\Paiement;
use App\Entity\User;
use App\Form\ClientType;
use App\Repository\UserRepository;
use App\Service\ApiService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Stripe\Stripe;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class RegistrationController extends AbstractController
{
    /**
     * @Route("/registration", name="registration")
     */
    public function index(Request $request, SessionInterface $session, UserRepository $userRepository): Response
    {
        
        $priceId = '';

        /**
         * Si le client se pointe à partir de la page tarifs
         */
        if ($request->get('price_id')) {
            //dd($request->get('price_id'));
            $priceId = $request->get('price_id');
            $session->set('price_id', $priceId);
        }
        
        $newClient = new Client();
        
        $form = $this->createForm(ClientType::class, $newClient);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            //dd($newClient);
            /**
             * Récupération du tarif choisi par l'utilisateur
             */
            if ($request->request->get('client')['type_abonnement']) {
                $priceId = $request->request->get('client')['type_abonnement'];
                $session->set('price_id', $priceId);
            }
            
            //Les informations de la première étape seront enregistrées dans la premimère étape et seront flushées si l'utiliseur valide son abonnement et qu'il obtient un vd
            //Le User relatif à ce client ne sera créé que lorsque les deux dernières étapes (càd le paiement et la création d'un compte sur Lenbox seront validées) 
            $userExistence = $userRepository->findBy(['email' => $newClient->getEmail()]);
            
            if ($userExistence) {

                $this->addFlash('danger', 'Cet e-mail est déjà relié à un utilisateur');

                return $this->redirectToRoute('registration');
            }

            $session->set('possibleNewUser', $newClient);

            if ($session->get('possibleNewUser')) 
            {
                return $this->redirectToRoute('registration_second_step');
            } else {
                $this->addFlash('danger', 'Il y a eu un problème, veuillez ressoummettre le formulaire !!!');
            }

        }

        return $this->render('registration/index.html.twig', [
            'controller_name' => 'RegistrationController',
            'form' => $form->createView(),
            'priceId' => $priceId
        ]);
    }

    /**
     * @Route("/registration/second-step", name="registration_second_step")
     */
    public function registrationSeconStep(SessionInterface $session)
    {
        if ($session->get('price_id')) {
            $priceId = $session->get('price_id');
         } else {
            $this->addFlash('danger', 'Aucun type d\'abonnement n\'a été choisi');
             return $this->redirectToRoute('registration');
         }
 
         $priceArray = [
             'price_1JWc1BBW8SyIFHAgvuKoItbD',
             'price_1JT0YJBW8SyIFHAgmEuizs6Z',
             'price_1JWc1oBW8SyIFHAgGnAmvtyw',
             'price_1JWc3mBW8SyIFHAg2E4YGU4c'
         ];
 
         if (!in_array($priceId, $priceArray)) {
            $this->addFlash('danger', 'Le type d\'abonnement que vous avez choisi n\'existe pas');
            return $this->redirectToRoute('registration');
         }

        //dd($session->get('possibleNewUser'));
        return $this->render('registration/secondStepRegistration_trial.html.twig');
    }

    /**
     * @Route("/registration/payment", name="registration_payment") 
     */
    public function registrationPayment(SessionInterface $session):Response
    {
        Stripe::setApiKey($_ENV['STRIPE_SECRET']);
        $priceId = 'price_1JT0YJBW8SyIFHAgmEuizs6Z';

        if ($session->get('price_id')) {
           $priceId = $session->get('price_id');
        } else {
            $this->addFlash('danger', 'Aucun type d\'abonnement n\'a été choisi');
            return $this->redirectToRoute('registration');
        }

        $priceArray = [
            'price_1JWc1BBW8SyIFHAgvuKoItbD',
            'price_1JT0YJBW8SyIFHAgmEuizs6Z',
            'price_1JWc1oBW8SyIFHAgGnAmvtyw',
            'price_1JWc3mBW8SyIFHAg2E4YGU4c'
        ];

        if (!in_array($priceId, $priceArray)) 
        {
            $this->addFlash('danger', 'Le type d\'abonnement que vous avez choisi n\'existe pas');
            return $this->redirectToRoute('registration');
        }

        //Local success_url :
        //'success_url' => 'http://localhost:8000/registration/payment/success?session_id={CHECKOUT_SESSION_ID}',
        //Production success_url :
        //'success_url' => 'http://femcreditconso.fr/registration/payment/success?session_id={CHECKOUT_SESSION_ID}',
        $success_url = $_ENV['REGISTRATION_SUCCESS_URL'];

        $paymentSession = \Stripe\Checkout\Session::create([
            'success_url' => $success_url,
            'cancel_url' => $this->generateUrl('registration_payment_failed', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'payment_method_types' => ['card'],
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $priceId,
                // For metered billing, do not pass quantity
                'quantity' => 1,
            ]],
        ]);

        return $this->redirect($paymentSession->url, 303);
    }

    /**
     * @Route("/registration/payment/success", name="registration_payment_success")
     */
    public function registrationPaymentSuccess(Request $request, SessionInterface $session, EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder, ApiService $apiService):Response
    {
        $session_id = $request->get('session_id');

        Stripe::setApiKey($_ENV['STRIPE_SECRET']);

        $stripe_session = \Stripe\Checkout\Session::retrieve(
          $session_id,
          []
        );

        $potentialClient = $session->get('possibleNewUser');
        //Création d'un nouvel abonnement
        $nouvelAbonnementPotentiel = new Abonnement();
        
        $nouvelAbonnementPotentiel->setStripeSubscriptionId($stripe_session->subscription);
        $nouvelAbonnementPotentiel->setStripeCusId($stripe_session->customer);
        $nouvelAbonnementPotentiel->setMode($stripe_session->mode);
        $nouvelAbonnementPotentiel->setStatutPaiement($stripe_session->payment_status);
        $nouvelAbonnementPotentiel->setDateDebutAbonnement(new DateTime());
        $nouvelAbonnementPotentiel->setClient($potentialClient);
        
        $session->set('abonnementPotentiel', $nouvelAbonnementPotentiel);
       
        //Création de l'entité user relatif au client
        $userRelatedToPotentialClient = new User;
        
        //Password encrypting
        $encryptedPassword = $passwordEncoder->encodePassword($userRelatedToPotentialClient, $potentialClient->getPassword());

        $userRelatedToPotentialClient->setEmail($potentialClient->getEmail());
        $userRelatedToPotentialClient->setPassword($encryptedPassword);
        $userRelatedToPotentialClient->setDateCreationUtilisateur(new DateTime());
        $userRelatedToPotentialClient->setActive(true);
        $userRelatedToPotentialClient->setRoles(["ROLE_CLIENT"]);
        $uniqId = md5(uniqid());
        $clientsInfosFromLenbox = $apiService->postLenbox($potentialClient->getNomEntreprise(), $potentialClient->getEmail(), $potentialClient->getTelMobile(), $uniqId);
        $clientsVd = $clientsInfosFromLenbox['response']['vd'];
        
        //Données client à enregistrer
        $potentialClient->setUniqid($uniqId);
        $potentialClient->setUniqid($uniqId);
        $potentialClient->setVd($clientsVd);
        $potentialClient->setStripeToken(md5(uniqid()));
        $potentialClient->setPassword($encryptedPassword);
        $potentialClient->setUser($userRelatedToPotentialClient);
        $potentialClient->setAbonnement($nouvelAbonnementPotentiel);        

        $em->persist($potentialClient);
        $em->flush();
    
        //Création de la facture potentielle relative à l'abonneement
        $nouvelleFacturePotentielle = new Facture;

        $statutFacture = $stripe_session->payment_status === "paid" ? true : false;
 
        $nouvelleFacturePotentielle->setDateEmissionFacture(new DateTime());
        $nouvelleFacturePotentielle->setFactureAcquitee($statutFacture);
        $nouvelleFacturePotentielle->setMontantTtcFacture($stripe_session->amount_total/100);
        $nouvelleFacturePotentielle->setAbonnement($nouvelAbonnementPotentiel);
        $nouvelleFacturePotentielle->setPourcentageTva(20);
 
        $session->set('facturePotentielle', $nouvelleFacturePotentielle);
        $facturePotentielle = $session->get('facturePotentielle');

        $em->persist($facturePotentielle);
        $em->flush();

        //Création du paiement relatif à l'abonnement (et donc à la facture)
        $paiement = new Paiement();
        $paiement->setMontantTtc($stripe_session->amount_total/100);
        $paiement->setPaid(true);
        $paiement->setPaidAt(new DateTimeImmutable());
        $paiement->setFacture($nouvelleFacturePotentielle);

        $nouvelleFacturePotentielle->addPaiement($paiement);

        $em->persist($paiement);
        $em->flush();

        return $this->render('registration/successPayment.html.twig');
    }

    /**
     * @Route("/registration/payment/failed", name="registration_payment_failed")
     */
    public function registrationPaymentFailed():Response
    {
        //paymentFailure.html.twig
        return $this->render('registration/paymentFailure.html.twig');
    }
}
