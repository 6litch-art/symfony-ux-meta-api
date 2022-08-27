<?php

namespace Meta\Facebook\Subscriber;

use Twig\Environment;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class FacebookListener
{
    private $twig;

    private $pixelId;
    private $enable = false;

    public function __construct(RequestStack $requestStack, ParameterBagInterface $parameterBag, Environment $twig)
    {
        $this->twig = $twig;
        $this->parameterBag = $parameterBag;
        $this->requestStack = $requestStack;
    }

    public function isProfiler($event)
    {
        $route = $event->getRequest()->get('_route');
        return str_starts_with($route, "_wdt") || str_starts_with($route, "_profiler");
    }

    public function isEasyAdmin()
    {
        $request = $this->requestStack->getCurrentRequest();

        $controllerAttribute = $request->attributes->get("_controller");
        $array = is_array($controllerAttribute) ? $controllerAttribute : explode("::", $request->attributes->get("_controller"));
        $controller = explode("::", $array[0])[0];

        $parents = [];
        $parent = $controller;

        while(class_exists($parent) && ( $parent = get_parent_class($parent)))
            $parents[] = $parent;

        $eaParents = array_filter($parents, fn($c) => str_starts_with($c, "EasyCorp\Bundle\EasyAdminBundle"));
        return !empty($eaParents);
    }

    private function allowRender(ResponseEvent $event)
    {
        if (!$this->enable)
            return false;

        if(!$this->pixelId) return;

        if($this->isEasyAdmin())
            return false;

        $contentType = $event->getResponse()->headers->get('content-type');
        if ($contentType && !str_contains($contentType, "text/html"))
            return false;

        if (!$event->isMainRequest())
            return false;

        return !$this->isProfiler($event);
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $this->enable     = $this->parameterBag->get("facebook.enable");
        if (!$this->enable)
            return false;

        $this->domainVerificationKey     = $this->parameterBag->get("facebook.domainVerificationKey");
        if($this->domainVerificationKey) {

            $meta = "<meta name='facebook-domain-verification' content='".$this->domainVerificationKey."' />";

            $this->twig->addGlobal("meta_facebook", array_merge(
                $this->twig->getGlobals()["meta_facebook"] ?? [],
                ["meta" => ($this->twig->getGlobals()["meta_facebook"]["meta"] ?? "") . $meta]
            ));
        }

        $this->pixelId     = $this->parameterBag->get("facebook.pixelId");
        if(!$this->pixelId) return;

        $this->autoAppend = $this->parameterBag->get("facebook.autoappend");
        if (!$this->autoAppend)
            return false;

        $javascript =
            "<!-- Facebook Pixel Code -->
            <script>
                !function(f,b,e,v,n,t,s)
                {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                n.callMethod.apply(n,arguments):n.queue.push(arguments)};
                if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
                n.queue=[];t=b.createElement(e);t.async=!0;
                t.src=v;s=b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t,s)}(window, document,'script',
                'https://connect.facebook.net/en_US/fbevents.js');
                fbq('init', '".$this->pixelId."');
                fbq('track', 'PageView');
            </script>
            <noscript>
                <img height='1' width='1' style='display:none' src='https://www.facebook.com/tr?id=".$this->pixelId."&ev=PageView&noscript=1'/>
            </noscript>
            <!-- End Facebook Pixel Code -->";

        $this->twig->addGlobal("meta_facebook", array_merge(
            $this->twig->getGlobals()["meta_facebook"] ?? [],
            ["javascript" => ($this->twig->getGlobals()["meta_facebook"]["javascript"] ?? "") . $javascript]
        ));
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$this->allowRender($event)) return false;

        $response = $event->getResponse();

        $meta = $this->twig->getGlobals()["meta_facebook"]["meta"] ?? "";
        $content = preg_replace(['/<\/head\b[^>]*>/'], [$meta."$0"], $response->getContent(), 1);

        if (!$this->autoAppend) {

            $javascript = $this->twig->getGlobals()["meta_facebook"]["javascript"] ?? "";
            $content = preg_replace(['/<\/head\b[^>]*>/'], [$javascript."$0"], $response->getContent(), 1);
        }

        if(!is_instanceof($response, [StreamedResponse::class, BinaryFileResponse::class]))
            $response->setContent($content);

        return true;
    }

}
