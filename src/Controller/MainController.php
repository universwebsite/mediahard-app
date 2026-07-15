<?php

namespace App\Controller;

use App\Entity\Items; // Ваша сущность называется Items
use App\Service\OllamaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MainController extends AbstractController
{
    #[Route('/', name: 'app_main')]
    public function index(\App\Repository\ItemsRepository $itemsRepository): Response
    {
        $activeItems = $itemsRepository->findBy([], ['id' => 'DESC'], 3);
        return $this->render('main/index.html.twig', ['items' => $activeItems]);
    }

    #[Route('/item/{id}', name: 'app_item_show', methods: ['GET'])]
    public function show(Items $item, OllamaService $ollamaService, EntityManagerInterface $em): Response
    {
        // Проверяем: если поле aiPassportHtml пустое — запускаем автогенерацию паспорта
        if (empty($item->getAiPassportHtml())) {
            $this->addFlash('info', 'Генерируем ИИ‑паспорт для товара… Это займёт пару секунд.');
            
            // Вызываем генератор паспорта
            $html = $ollamaService->generatePassport($item);
            $item->setAiPassportHtml($html);
            
            // Намертво фиксируем изменения в PostgreSQL 15 старого сайта
            $em->flush();
        }

        return $this->render('main/show.html.twig', [
            'item' => $item,
        ]);
    }
}
