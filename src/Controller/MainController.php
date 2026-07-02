<?php

namespace App\Controller;

use App\Repository\ItemsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MainController extends AbstractController
{
    #[Route('/', name: 'app_main')]
    public function index(ItemsRepository $itemsRepository): Response
    {
        $activeItems = $itemsRepository->findBy([], ['id' => 'DESC'], 3);

        return $this->render('main/index.html.twig', [
            'items' => $activeItems,
        ]);
    }

    #[Route('/item/{id}', name: 'app_item_show', methods: ['GET'])]
    public function show(int $id, ItemsRepository $itemsRepository): Response
    {
        // 🛢️ Ищем конкретный товар в базе данных по его ID
        $item = $itemsRepository->find($id);

        // Если товар не найден (например, ввели несуществующий ID), отдаем 404 ошибку
        if (!$item) {
            throw $this->createNotFoundException('Данный компонент не найден на складе MediaHard.');
        }

        return $this->render('main/show.html.twig', [
            'item' => $item,
        ]);
    }
}
