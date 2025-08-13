<?php

namespace App\Controller\Api;

use App\Entity\Investment;
use App\Entity\Owner;
use App\Repository\InvestmentRepository;
use App\Repository\OwnerRepository;
use App\Service\InvestmentService;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/investments')]
class InvestmentController extends AbstractController
{
    public function __construct(
        private InvestmentService $investmentService,
        private ManagerRegistry $doctrine,
        private OwnerRepository $ownerRepository,
        private InvestmentRepository $investmentRepository,
    ) {}

    /**
     * Verifica se a API está funcionando (Ping-Pong)
     */
    #[Route('', methods: ['POST'])]
    public function ping(): JsonResponse
    {
        return $this->json([
            "status" => "Success",
            'response' => "Ping-Pong API trabalhando!",
        ], 201);
    }

    /**
     * Criar investidor (Owner)
     */
    #[Route('/newOwner', methods: ['POST'])]
    public function new_owner(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if ($data === null) {
                $data = $request->request->all();
            }

            // Validar nome do investidor
            if (empty($data['name'])) {
                throw new \InvalidArgumentException('Nome do investidor é obrigatório!');
            }

            // Validar email do investidor
            if (empty($data['email'])) {
                throw new \InvalidArgumentException('Email do investidor é obrigatório!');
            }
            
            // Criar investimento
            $owner = new Owner();

            // Seta parametros
            $owner->setName($data['name']);
            $owner->setEmail($data['email']);

            // Persistir
            $em = $this->doctrine->getManager();
            $em->persist($owner);
            $em->flush();
            
            return $this->json([
                "status" => "Success",
                'id' => $owner->getId(),
                'name' => $owner->getName(),
                'email' => $owner->getEmail(),
            ], 201);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Atenção : ' . $e->getMessage()
            ], 428);
        }
    }

    /**
     * Criar um novo investimento
     */
    #[Route('/create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if ($data === null) {
                $data = $request->request->all();
            }
            
            // Validar se owner foi informado
            if (empty($data['ownerId'])) {
                throw new \InvalidArgumentException('Id do investidor é obrigatório!');
            }

            // Verificar owner existe
            $owner = $this->ownerRepository->find($data['ownerId']);
            if (!$owner) {
                throw new \InvalidArgumentException('Investidor não encontrado!');
            }

            // Validar se valor foi informado
            if (empty($data['initialValue'])) {
                throw new \InvalidArgumentException('Valor inicial é obrigatório!');
            }

            // Valor inicial >= 0
            $initialValue = (float) $data['initialValue'];
            if ($initialValue <= 0) {
                throw new \InvalidArgumentException('Valor inicial inválido!');
            }

            // Verificar se a data foi informada
            if(!isset($data['createdAt']) || empty($data['createdAt'])) {
                throw new \InvalidArgumentException('Data de inicio do investimento não informado!');
            }
            
            // Verificar se a data é válida
            $date = DateTime::createFromFormat('Y-m-d', $data['createdAt']);
            if (!$date || $date->format('Y-m-d') !== $data['createdAt']) {
                throw new \InvalidArgumentException('Data de inicio do investimento inválida!');
            }

            // Verificar se é data futura
            if ($date > new DateTime('now')) {
                throw new \InvalidArgumentException('Data futura não é permitida, somente data atual ou data do passado!');
            }

            // Criar investimento
            $investment = new Investment();

            // Seta parametros
            $investment->setOwner($owner);
            $investment->setInitialValue($initialValue);
            $investment->setCreatedAt($date);
            
            // Persistir
            $em = $this->doctrine->getManager();
            $em->persist($investment);
            $em->flush();
            
            return $this->json([
                "status" => "Success",
                'id' => $investment->getId(),
                'ownerId' => $owner->getId(),
                'createdAt' => $investment->getCreatedAt()->format('Y-m-d'),
                'initialValue' => $investment->getInitialValue(),
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Atenção : ' . $e->getMessage()
            ], 428);
        }
    }

    /**
     * Visualizar detalhes de um investimento com saldo apurado
     */
    #[Route('/show/{id}', methods: ['POST'])]
    public function show(int $id, Request $request): JsonResponse
    {
        try {
            // Verificar se o id foi informado
            if (empty($id) || $id <= 0) {
                throw new \InvalidArgumentException('Id do investimento inválido!');
            }

            // Verificar se o investimento existe
            $investment = $this->investmentRepository->find($id);
            if (!$investment) {
                return $this->json(['error' => 'Investimento não encontrado'], 404);
            }

            // Verifica se o investimento já foi resgatado
            $redeemedAt = $investment->getRedeemedAt();
            if ($redeemedAt !== null) {
                return $this->json([
                    'Status' => 'Success',
                    'id' => $investment->getId(),
                    'ownerId' => $investment->getOwner()->getId(),
                    'createdAt' => $investment->getCreatedAt()->format('Y-m-d'),
                    'initialValue' => $investment->getInitialValue(),
                    'redeemed' => $investment->getRedeemedAt() !== null,
                    'redeemedAt' => $investment->getRedeemedAt()?->format('Y-m-d'),
                    'redeemedValue' => $investment->getRedeemedValue(),
                ], 201);
            }

            $data = json_decode($request->getContent(), true);
            if ($data === null) {
                $data = $request->request->all();
            }

            // caso a data nao for passada pega o data atual
            if(!isset($data['redeemDate']) || empty($data['redeemDate'])) {
                $data['redeemDate'] = date('Y-m-d');
            }
            
            // Verificar se a data é válida
            $date = DateTime::createFromFormat('Y-m-d', $data['redeemDate']);
            if (!$date || $date->format('Y-m-d') !== $data['redeemDate']) {
                throw new \InvalidArgumentException('Data de resgate do investimento inválida!');
            }
            
            // Verificar se a data é futura ou anterior à data de criação do investimento
            if ($date > new DateTime('now') || $date < $investment->getCreatedAt() ) {
                throw new \InvalidArgumentException('Data não permitida, somente data posterior a data do investimento até a data atual!');
            }
            
            $result = $this->investmentService->redeemInvestment($investment, $date);
            
            // Atualiza investimento para registrar/salvar o resgate
            $investment->setRedeemedAt($date);
            $investment->setRedeemedValue($result['net']);

            return $this->json([
                'status' => 'Success',
                'investment' => $investment->getId(),
                'ownerId' => $investment->getOwner()->getId(),
                'createdAt' => $investment->getCreatedAt()->format('Y-m-d'),
                'initialValue' => $investment->getInitialValue(),
                'redeemed' => false,
                'redeemedAt' => $investment->getRedeemedAt()?->format('Y-m-d'),
                'gross' => $result['gross'],
                'profit' => $result['profit'],
                'tax' => $result['tax'],
                'redeemedValue' => $result['net'],
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Atenção : ' . $e->getMessage()
            ], 428);
        }
    }

    private function showRendiment()
    {
        return [];
    }

    /**
     * Resgatar um investimento
     */
    #[Route('/redeem/{id}', methods: ['POST'])]
    public function redeem(int $id, Request $request): JsonResponse
    {
        try {

            // Verificar se o investimento existe
            $investment = $this->investmentRepository->find($id);
            if (!$investment) {
                throw new \InvalidArgumentException('Investimento não encontrado!');
            }
            
            // Verificar se o investimento já foi resgatado
            if ($investment->getRedeemedAt() !== null) {
                throw new \InvalidArgumentException('Investimento já resgatado.');
            }
            
            $data = json_decode($request->getContent(), true);
            if ($data === null) {
                $data = $request->request->all();
            }
            
            // Verificar se a data foi informada
            if(!isset($data['redeemDate']) || empty($data['redeemDate'])) {
                throw new \InvalidArgumentException('A data de resgate do investimento não informado! (redeemDate)');
            }

            // Verificar se a data é válida
            $date = DateTime::createFromFormat('Y-m-d', $data['redeemDate']);
            if (!$date || $date->format('Y-m-d') !== $data['redeemDate']) {
                throw new \InvalidArgumentException('Data de resgate do investimento inválida!');
            }
            
            // Verificar se a data é futura ou anterior à data de criação do investimento
            if ($date > new DateTime('now') || $date < $investment->getCreatedAt() ) {
                throw new \InvalidArgumentException('Data não permitida, somente data posterior a data do investimento até a data atual!');
            }
            $result = $this->investmentService->redeemInvestment($investment, $date);

            // Atualiza investimento para registrar/salvar o resgate
            $investment->setRedeemedAt($date);
            $investment->setRedeemedValue($result['net']);

            // Persistir atualização do investimento (resgate)
            $em = $this->doctrine->getManager();
            $em->persist($investment);
            $em->flush();

            return $this->json([
                'status' => 'Success',
                'investment' => $investment->getId(),
                'createdAt' => $investment->getCreatedAt()->format('Y-m-d'),
                'initialValue' => $investment->getInitialValue(),
                'redeemed' => $investment->getRedeemedAt() !== null,
                'redeemedAt' => $investment->getRedeemedAt()?->format('Y-m-d'),
                'gross' => $result['gross'],
                'profit' => $result['profit'],
                'tax' => $result['tax'],
                'redeemedValue' => $result['net'],
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Atenção : ' . $e->getMessage()
            ], 428);
        }
    }

    /**
     * Listar investimentos de um proprietário com paginação
     */
    #[Route('/owner/{ownerId}', methods: ['POST'])]
    public function listByOwner(int $ownerId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if ($data === null) {
            $data = $request->request->all();
        }

        $owner = $this->ownerRepository->find($ownerId);
        if (!$owner) {
            throw new \InvalidArgumentException('Investidor não encontrado!');
        }

        $page = empty($data['page']) ? 1 : (int)$data['page'];
        $limit = empty($data['limit']) ? 100 : (int)$data['limit'];
        $offset = ($page - 1) * $limit;

        $repo = $this->investmentRepository;

        $total = (int) $repo->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();

        $res = $repo->createQueryBuilder('i')
            ->andWhere('i.owner = :owner')
            ->setParameter('owner', $owner)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('i.createdAt', 'DESC');

        $investments = $res->getQuery()->getResult();

        $listaInvestments = [];
        foreach ($investments as $investment) {
            $balance = $this->investmentService->calculateExpectedBalance($investment);
            $listaInvestments[] = [
                'id' => $investment->getId(),
                'createdAt' => $investment->getCreatedAt()->format('Y-m-d'),
                'initialValue' => $investment->getInitialValue(),
                'redeemed' => $investment->getRedeemedAt() !== null,
                'expectedBalance' => $investment->getRedeemedAt() !== null ? $investment->getRedeemedValue() : round($balance, 2),
            ];
        }

        return $this->json([
            "status" => "Success",
            'ownerId' => $ownerId,
            'owner' => $owner->getName(),
            'totalInvestment' => $total,
            'totalPages' => ceil($total / $limit),
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'listaInvestments' => $listaInvestments,
        ], 200);
    }
}
