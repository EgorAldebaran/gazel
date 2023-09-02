<?php  

namespace App\Controller;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use App\Entity\Trader;
use App\Form\RegistrationFormType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TraderController extends AbstractController
{
    public const TIME_EXPIRATION_TOKEN = 3600 * 24 * 7;
    
    #[Route('/room/register', name: 'register_room')]
    public function registerRoom(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, TokenGeneratorInterface $tokenGenerator, MailerInterface $mailer): Response
    {
        $trader = new Trader;
        
        $token = $tokenGenerator->generateToken();
        $url = $this->generateUrl('entry_room', ['token' => $token], UrlGenerator::ABSOLUTE_URL);
        
        $form = $this->createForm(RegistrationFormType::class, $trader);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $trader->setPassword(
                $userPasswordHasher->hashPassword(
                    $trader,
                    $form->get('plainPassword')->getData()
                )
            );
            $trader->setToken($token);

            $entityManager->persist($trader);
            $entityManager->flush();

            $email = (new TemplatedEmail())
                   ->from('duck@example.com')
                   ->to('OccultDebugger@yandex.ru')
                   ->subject('info info info')
                   ->htmlTemplate('email/signup.html.twig')
                   ->context([
                       'expiration_date' => new \DateTime('+7 days'),
                       'url' => $url,
                       'username' => $this->getUser(),
                   ]);
            $mailer->send($email);

            return $this->redirectToRoute('guest_room');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/room/login', name: 'login_room')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        //if ($this->getUser()) {
        //  return $this->redirectToRoute('target_path');
        //}

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        dd($lastUsername);

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route('/room/guest', name: 'guest_room')]
    public function guestRoom(): Response
    {
        return $this->render('trader/index.html.twig');
    }
    
    #[Route('/room/entry/{token}', name: 'entry_room')]
    public function entry(string $token, EntityManagerInterface $entityManager): Response
    {
        $trader = $entityManager->getRepository(Trader::class)->findOneBy(['token' => $token]);

        if ($trader == NULL) {
            throw new \Exception('трейдера с таким токеном нет в системе!');
        }

        $now = (new \DateTime)->getTimestamp();
        $token_init_time = $trader->getCreatedAt()->getTimestamp();

        if ($now - $token_init_time >= self::TIME_EXPIRATION_TOKEN) {
            throw new \Exception('Срок жизни токена истек!');
        }

        $trader->setIsVerified(true);
        
        $entityManager->persist($trader);
        $entityManager->flush();

        return $this->render('trader/index.html.twig', [
            'trader' => $trader
        ]);
    }

    #[Route('/room/send/email', name: 'send_email_room')]
    public function sendEmail(MailerInterface $mailer, TokenGeneratorInterface $tokenGenerator): Response
    {
        $token = $tokenGenerator->generateToken();
        $url = $this->generateUrl('entry_room', ['token' => $token], UrlGenerator::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
             ->from('duck@example.com')
             ->to('OccultDebugger@yandex.ru')
             ->subject('Look info email')
             ->htmlTemplate('email/signup.html.twig')
             ->context([
                   'expiration_date' => new \DateTime('+7 days'),
                   'url' => $url,
                   'username' => $this->getUser(),
               ]);
        $mailer->send($email);
        return new Response('send email');
    }

    #[Route('room/success', name: 'success_room')]
    public function sucessRoom(AuthenticationUtils $authenticationUtils, EntityManagerInterface $entityManager): Response
    {
        $trader = $entityManager->getRepository(Trader::class)->findOneBy(['email' => $authenticationUtils->getLastUsername()]);
        
        $lastUsername = $authenticationUtils->getLastUsername();
        $username = explode("@", $lastUsername)[0];

        $now = (new \DateTime)->getTimestamp();
        $token_init_time = $trader->getCreatedAt()->getTimestamp();

        //dd(3600 * 24 * 7);
        dd($now - $token_init_time);
        dd($token_init_time);
        dd($now);
        
        return $this->render('trader/success.html.twig', [
            'username' => $username
        ]);
    }
}
