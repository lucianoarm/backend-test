<?php

namespace App\Controller\Api;

use App\Entity\Investment;
use App\Entity\Owner;
use App\Repository\InvestmentRepository;
use App\Repository\OwnerRepository;
use App\Service\InvestmentService;
use App\Service\EmailService;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Model;

#[Route('/api/investments')]
class InvestmentController extends AbstractController
{
    public function __construct(
        private InvestmentService $investmentService,
        private EmailService $emailService,
        private ManagerRegistry $doctrine,
        private OwnerRepository $ownerRepository,
        private InvestmentRepository $investmentRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    #[OA\Get(
        path: "/api/investments",
        summary: "Inicialmente implementado para verifica se a API está Online",
        description: "Este endpoint retorna informações sobre o estado da API e suas URLs.",
        responses: [
            new OA\Response(
                response: 200,
                description: "Retorna um JSON, com as seguintes informações:<br> 
                                <b>status</b>: Status da API<br>
                                <b>url_doc</b>: Url para acessar a documentação pelo Swagger<br>
                                <b>url_json</b>: Url para acessar a API em formato JSON",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string"),
                        new OA\Property(property: "url_doc", type: "string"),
                        new OA\Property(property: "url_json", type: "string")
                    ]
                )
            )
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->emailService->sendEmail(
            $_ENV['EMAIL_TO'],
            'API de Investimentos',
            [
                'subject' => 'API de Investimentos - Api/Investments',
                'name' => 'Administrador',
                'message' => 'O endpoint de "investimentos está online" foi consultado.'
            ]
        );
        
        return $this->json([
            "status" => "Online",
            "url_doc" => $request->getSchemeAndHttpHost() . $request->getBasePath() . "/api/doc",
            "url_json" => $request->getSchemeAndHttpHost() . $request->getBasePath() . "/api/doc.json",
        ], 200);
    }

    #[Route('/newOwner', methods: ['POST'])]
    #[OA\Post(
        path: "/api/investments/newOwner",
        summary: "Endpoint para registrar/cadastrar um novo investidor!",
        description: "Este endpoint permite que um novo investidor seja registrado na API.<br>
        É necessário cadastrar um investidor para fazer uso dos proximos endpoints relacionados a investimentos.",
        requestBody: new OA\RequestBody(
            required: true,
            description: "Os campos <b>name</b> e <b>email</b> são obrigatórios.<br>
                          Os campos abaixo devem ser passados no Body da requisição.",
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "name", type: "string", description: "Nome do investidor", example: "Luciano Meneses"),
                    new OA\Property(property: "email", type: "string", description: "Email do investidor", example: "luciano@teste.com")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "<p>Cadastrado com sucesso, um JSON será retornado com as seguintes informações:</p><br>
                                <b>status</b>: Status da operação<br>
                                <b>id</b>: Id do investidor<br>
                                <b>name</b>: Nome do investidor<br>
                                <b>email</b>: Email do investidor",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "Success"),
                        new OA\Property(property: "id", type: "integer", example: 1),
                        new OA\Property(property: "name", type: "string", example: "Luciano Meneses"),
                        new OA\Property(property: "email", type: "string", example: "luciano@teste.com"),
                    ]
                )
            ),
            new OA\Response(
                response: 428, 
                description: "<p>Erro na operação, um JSON será retornado com as seguintes informações:</p><br>
                                <b>status</b>: Status da operação<br>
                                <b>message</b>: Mensagem de erro<br>",
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(
                            properties: [
                                new OA\Property(property: "status", type: "string", example: "Error"),
                                new OA\Property(property: "message", type: "string", example: "Atenção : Nome e email são obrigatórios!"),
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function new_owner(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true) ?: $request->request->all();
            if (empty($data['name']) || empty($data['email'])) {
                throw new \InvalidArgumentException('Nome e email são obrigatórios!');
            }

            $owner = new Owner();
            $owner->setName($data['name']);
            $owner->setEmail($data['email']);
            
            $em = $this->doctrine->getManager();
            $em->persist($owner);
            $em->flush();

            $this->emailService->sendEmail(
                $_ENV['EMAIL_TO'],
                'API de Investimentos',
                [
                    'subject' => 'API de Investimentos - Api/NewOwner',
                    'name' => 'Administrador',
                    'message' => 'O endpoint de "Criação de Novo Proprietário" foi executado.'
                ]
            );
            
            return $this->json([
                "status" => "Success",
                'id' => $owner->getId(),
                'name' => $owner->getName(),
                'email' => $owner->getEmail(),
            ], 201);

        } catch (\Exception $e) {
            return $this->json(['status' => 'Error','message' => 'Atenção : ' . $e->getMessage()], 428);
        }
    }

    #[Route('/create', methods: ['POST'])]
    #[OA\Post(
        path: "/api/investments/create",
        summary: "Endpoint para registrar/cadastrar um novo investimento de um investidor!",
        description: "Este endpoint permite que um novo investimento seja registrado na API.<br>
                      É necessário cadastrar um investimento para fazer uso dos proximos endpoints relacionados as consultas e resgate de investimentos.",
        requestBody: new OA\RequestBody(
            required: true,
            description: "Os campos <b>ownerId</b>, <b>initialValue</b> e <b>createdAt</b> são obrigatórios.<br>
                          Para o campo <b>initialValue</b>, será permitido somente valores acima de 0, não é permitido valores negativos ou zerados.<br>
                          Para o campo <b>createdAt</b>, será permitido data atual ou uma data do passado, não é permitida datas futuras.<br>
                          Os campos abaixo devem ser passados no Body da requisição.",
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "ownerId", type: "integer", description: "ID do investidor", example: 1),
                    new OA\Property(property: "initialValue", type: "number", description: "Valor inicial do investimento", example: 1000.00),
                    new OA\Property(property: "createdAt", type: "string", format: "date", description: "Data de criação do investimento", example: "2023-01-01"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "<p>Cadastrado com sucesso, um JSON será retornado com as seguintes informações:</p><br>
                                <b>status</b>: Status da operação<br>
                                <b>id</b>: Id do investimento<br>
                                <b>ownerId</b>: Id do investidor<br>
                                <b>creatAt</b>: Data de criação do investimento<br>
                                <b>initialValue</b>: Valor do investimento",

                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "Success"),
                        new OA\Property(property: "id", type: "integer", example: 1),
                        new OA\Property(property: "ownerId", type: "integer", example: 1),
                        new OA\Property(property: "createdAt", type: "string", example: "2025-08-13"),
                        new OA\Property(property: "initialValue", type: "string", example: "1000"),
                    ]
                )
            ),
            new OA\Response(
                response: 428, 
                description: "<p>Erro na operação, um JSON será retornado com as seguintes informações:</p><br>
                                <b>status</b>: Status da operação<br>
                                <b>message</b>: Mensagem de erro<br>",
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(
                            properties: [
                                new OA\Property(property: "status", type: "string", example: "Error"),
                                new OA\Property(property: "message", type: "string", example: "Atenção : Todos os campos são obrigatórios!"),
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true) ?: $request->request->all();
            if (empty($data['ownerId']) || empty($data['initialValue']) || empty($data['createdAt'])) {
                throw new \InvalidArgumentException('Todos os campos são obrigatórios!');
            }

            $owner = $this->ownerRepository->find($data['ownerId']);
            if (!$owner) {
                throw new \InvalidArgumentException('Investidor não encontrado!');
            } 

            $initialValue = (float) $data['initialValue'];
            if ($initialValue <= 0) {
                throw new \InvalidArgumentException('Valor inicial inválido!');
            }

            $date = DateTime::createFromFormat('Y-m-d', $data['createdAt']);
            if (!$date || $date > new DateTime('now')) {
                throw new \InvalidArgumentException('Data inválida!');
            }

            $investment = new Investment();
            $investment->setOwner($owner);
            $investment->setInitialValue($initialValue);
            $investment->setCreatedAt($date);

            $em = $this->doctrine->getManager();
            $em->persist($investment);
            $em->flush();

            $this->emailService->sendEmail(
                $_ENV['EMAIL_TO'],
                'API de Investimentos',
                [
                    'subject' => 'API de Investimentos - Api/Create',
                    'name' => 'Administrador',
                    'message' => 'O endpoint de "Criação de Novo Investimento" foi executado.'
                ]
            );
            
            return $this->json([
                "status" => "Success",
                'id' => $investment->getId(),
                'ownerId' => $owner->getId(),
                'createdAt' => $investment->getCreatedAt()->format('Y-m-d'),
                'initialValue' => $investment->getInitialValue(),
            ], 201);

        } catch (\Exception $e) {
            return $this->json(['status' => 'Error','message' => 'Atenção : ' . $e->getMessage()], 428);
        }
    }

    #[Route('/show/{id}', methods: ['POST'])]
    #[OA\Post(
        path: "/api/investments/show/{id}",
        summary: "Endpoint para visualizar um determinado investimento de um investidor!",
        description: "Este endpoint permite visualizar detalhes de um investimento na API.<br>
                      É necessário informar o <b>id</b> do investimento para obter os detalhes.",
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            description: "Os campos <b>redeemDate</b>, não é obrigatório, se não informar, assume a data atual.<br>
                          No campo <b>redeemDate</b>, será permitido uma data entre a data da criação ate a data atual.<br>
                          Investimentos resgatados, <b>redeemDate</b> assumira a data do resgate.<br>
                          O campo abaixo devem ser passados no Body da requisição.",
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "redeemDate", type: "string", format: "date", description: "Data resgate do investimento", example: "2023-01-01"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "<p>Visualização de um investimento, um JSON será retornado com as seguintes informações:</p><br>
                                <b>status</b>: Status da operação<br>
                                <b>investment</b>: Id unico do investimento<br>
                                <b>ownerId</b>: Id unico do investidor<br>
                                <b>createdAt</b>: Data em que o investimento foi criado<br>
                                <b>initialValue</b>: Valor inicial do investimento<br>
                                <b>redeemed</b>: Booleano indicando se o investimento foi resgatado<br>
                                <b>redeemedAt</b>: Data usada como base de cálculo do resgate<br>
                                <b>gross</b>: Valor bruto do investimento (em relação a data base)<br>
                                <b>profit</b>: Lucro do investimento (em relação a data base)<br>
                                <b>tax</b>: Imposto do investimento (em relação a data base)<br>
                                <b>redeemedValue</b>: Valor líquido resgatado do investimento (em relação a data base)<br>",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "Success"),
                        new OA\Property(property: "investment", type: "integer", example: 1),
                        new OA\Property(property: "ownerId", type: "integer", example: 1),
                        new OA\Property(property: "createdAt", type: "string", example: "2020-08-13"),
                        new OA\Property(property: "initialValue", type: "string", example: "1200.00"),
                        new OA\Property(property: "redeemed", type: "string", example: "false"),
                        new OA\Property(property: "redeemedAt", type: "string", example: "2021-08-13"),
                        new OA\Property(property: "gross", type: "string", example: "1638.06"),
                        new OA\Property(property: "profit", type: "string", example: "438.06"),
                        new OA\Property(property: "tax", type: "string", example: "65.71"),
                        new OA\Property(property: "redeemedValue", type: "string", example: "1572.35"),
                    ]
                )
            ),
            new OA\Response(
                response: 428, 
                description: "<p>Erro na operação, um JSON será retornado com as seguintes informações:</p><br>
                                <b>status</b>: Status da operação<br>
                                <b>message</b>: Mensagem de erro<br>",
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(
                            properties: [
                                new OA\Property(property: "status", type: "string", example: "Error"),
                                new OA\Property(property: "message", type: "string", example: "Atenção : Investimento não encontrado!"),
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function show(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true) ?: $request->request->all();

            if (empty($id) || $id <= 0) {
                throw new \InvalidArgumentException('Id inválido!');
            }

            $investment = $this->investmentRepository->find($id);
            if (!$investment) {
                throw new \InvalidArgumentException('Investimento não encontrado!');
            }

            $redeemed = $investment->getRedeemedAt() !== null;
            if($redeemed) {
                $data['redeemDate'] = $investment->getRedeemedAt()->format('Y-m-d');
            }

            if(!$redeemed && empty($data['redeemDate'])) {
                $data['redeemDate'] = (new DateTime('now'))->format('Y-m-d');
            }

            $date = DateTime::createFromFormat('Y-m-d', $data['redeemDate']);
            if (!$date || $date > new DateTime('now') || $date < $investment->getCreatedAt()) {
                throw new \InvalidArgumentException('Data inválida!');
            }

            $result = $this->investmentService->redeemInvestment($investment, $date);

            $investment->setRedeemedAt($date);

            $this->emailService->sendEmail(
                $_ENV['EMAIL_TO'],
                'API de Investimentos',
                [
                    'subject' => 'API de Investimentos - Api/Show',
                    'name' => 'Administrador',
                    'message' => 'O endpoint de "Exibição de Investimento" foi executado.'
                ]
            );

            return $this->json([
                'status' => 'Success',
                'investment' => $investment->getId(),
                'ownerId' => $investment->getOwner()->getId(),
                'createdAt' => $investment->getCreatedAt()->format('Y-m-d'),
                'initialValue' => $investment->getInitialValue(),
                'redeemed' => $redeemed,
                'redeemedAt' => $investment->getRedeemedAt()->format('Y-m-d'),
                'gross' => $redeemed ? null : $result['gross'],
                'profit' => $redeemed ? null : $result['profit'],
                'tax' => $redeemed ? null : $result['tax'],
                'redeemedValue' => $redeemed ? $investment->getRedeemedValue() : $result['net'],
            ], 200);

        } catch (\Exception $e) {
            return $this->json(['status' => 'Error','message' => 'Atenção : ' . $e->getMessage()], 428);
        }
    }

    #[Route('/redeem/{id}', methods: ['POST'])]
    #[OA\Post(
        path: "/api/investments/redeem/{id}",
        summary: "Endpoint para efetuar o resgate de um determinado investimento!",
        description: "Este endpoint permite efetuar o resgate de um investimento na API.<br>
                      É necessário informar o <b>id</b> do investimento para efetuar o resgate e passar parametros no BODY.",
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Os campos <b>redeemDate</b>, é obrigatório.<br>
                          É permitido uma data entre a data da criação do investimento até a data atual no campo <b>redeemDate</b>,.<br>
                          O campo abaixo devem ser passados no Body da requisição.",
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "redeemDate", type: "string", format: "date", description: "Data resgate do investimento", example: "2023-01-01"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "<p>Resgate de um investimento, um JSON será retornado com as seguintes informações:</p><br>
                                <b>status</b>: Status da operação<br>
                                <b>investment</b>: Id do investimento<br>
                                <b>ownerId</b>: Id do investidor<br>
                                <b>createdAt</b>: Data de criação do investimento<br>
                                <b>initialValue</b>: Valor inicial do investimento<br>
                                <b>redeemed</b>: Indicando se o investimento foi resgatado<br>
                                <b>redeemedAt</b>: Data usada como base de cálculo do resgate<br>
                                <b>gross</b>: Valor bruto do investimento (em relação a data base)<br>
                                <b>profit</b>: Lucro do investimento (em relação a data base)<br>
                                <b>tax</b>: Imposto do investimento (em relação a data base)<br>
                                <b>redeemedValue</b>: Valor líquido resgatado do investimento (em relação a data base)<br>",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "Success"),
                        new OA\Property(property: "investment", type: "integer", example: 1),
                        new OA\Property(property: "ownerId", type: "integer", example: 1),
                        new OA\Property(property: "createdAt", type: "string", example: "2020-08-13"),
                        new OA\Property(property: "initialValue", type: "string", example: "1200.00"),
                        new OA\Property(property: "redeemed", type: "string", example: "false"),
                        new OA\Property(property: "redeemedAt", type: "string", example: "2021-08-13"),
                        new OA\Property(property: "gross", type: "string", example: "1638.06"),
                        new OA\Property(property: "profit", type: "string", example: "438.06"),
                        new OA\Property(property: "tax", type: "string", example: "65.71"),
                        new OA\Property(property: "redeemedValue", type: "string", example: "1572.35"),
                    ]
                )
            ),
            new OA\Response(
                response: 428, 
                description: "<p>Erro na operação, um JSON será retornado com as seguintes informações:</p><br>
                                <b>status</b>: Status da operação<br>
                                <b>message</b>: Mensagem de erro<br>",
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(
                            properties: [
                                new OA\Property(property: "status", type: "string", example: "Error"),
                                new OA\Property(property: "message", type: "string", example: "Atenção : Investimento não encontrado!"),
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function redeem(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true) ?: $request->request->all();

            if (empty($id) || $id <= 0) {
                throw new \InvalidArgumentException('Id inválido!');
            }

            $investment = $this->investmentRepository->find($id);
            if (!$investment || $investment->getRedeemedAt() !== null) {
                throw new \InvalidArgumentException('Investimento inválido ou já resgatado');
            }

            if (!isset($data['redeemDate']) || empty($data['redeemDate'])) {
                throw new \InvalidArgumentException('Data de resgate obrigatória');
            }
            

            $date = DateTime::createFromFormat('Y-m-d', $data['redeemDate']);
            if (!$date || $date > new DateTime('now') || $date < $investment->getCreatedAt()) {
                throw new \InvalidArgumentException('Data inválida!');
            }

            $result = $this->investmentService->redeemInvestment($investment, $date);

            $date = DateTime::createFromFormat('Y-m-d', $data['redeemDate']);
            $result = $this->investmentService->redeemInvestment($investment, $date);

            $investment->setRedeemedAt($date);
            $investment->setRedeemedValue($result['net']);
            
            $em = $this->doctrine->getManager();
            $em->persist($investment);
            $em->flush();

            $this->emailService->sendEmail(
                $_ENV['EMAIL_TO'],
                'API de Investimentos',
                [
                    'subject' => 'API de Investimentos - Api/Redeem',
                    'name' => 'Administrador',
                    'message' => 'O endpoint de "Resgate de Investimento" foi executado.'
                ]
            );
            
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
            return $this->json(['status' => 'Error','message' => 'Atenção : ' . $e->getMessage()], 428);
        }
    }

    #[Route('/owner/{ownerId}', methods: ['POST'])]
    #[OA\Post(
        path: "/api/investments/owner/{ownerId}",
        summary: "Endpoint para efetuar a consulta de todos os investimentos de um investidor!",
        description: "Este endpoint permite buscar todos os investimentos de um investidor na API.<br>
                      É necessário informar o <b>ownerId</b> do investidor para efetuar a consulta e passar parametros no BODY.",
        parameters: [
            new OA\Parameter(name: "ownerId", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Os campos <b>page</b> e <b>limit</b> são obrigatórios.<br>
                          O campo <b>page</b> vai setar a pagina que será apresentada.<br>
                          O campo <b>limit</b> vai setar a quantidade de itens que será apresentada por pagina.<br>
                          O campo abaixo devem ser passados no Body da requisição.",
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "page", type: "integer", description: "Número da página para paginação", example: 1),
                    new OA\Property(property: "limit", type: "integer", description: "Número de itens por página", example: 100),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "<p>Resgate de um investimento, um JSON será retornado com as seguintes informações:</p><br>
                                <b>status</b>: Status da operação<br>
                                <b>ownerId</b>: Id do investidor<br>
                                <b>owner</b>: Nome do investidor<br>
                                <b>totalInvestment</b>: Total de investimentos do investidor<br>
                                <b>totalPages</b>: Total de páginas disponíveis<br>
                                <b>page</b>: Página atual<br>
                                <b>limit</b>: Limite de itens por página<br>
                                <b>investments</b>: Lista de investimentos do investidor<br><br>
                                <p>Na lista investments, um vetor com objetos JSON será retornado com as seguintes informações:</p><br>
                                    <b>id</b>: Id do investimento<br>
                                    <b>createdAt</b>: Data de criação do investimento<br>
                                    <b>initialValue</b>: Valor inicial do investimento<br>
                                    <b>redeemed</b>: Indicando se o investimento foi resgatado<br>
                                    <b>expectedBalance</b>: Valor esperado do investimento<br>",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "Success"),
                        new OA\Property(property: "ownerId", type: "integer", example: 1),
                        new OA\Property(property: "owner", type: "string", example: "Luciano Meneses"),
                        new OA\Property(property: "totalInvestment", type: "integer", example: 3),
                        new OA\Property(property: "totalPages", type: "integer", example: 3),
                        new OA\Property(property: "page", type: "integer", example: 1),
                        new OA\Property(property: "limit", type: "integer", example: 100),
                        new OA\Property(
                            property: "listaInvestments",
                            type: "array",
                            example: [
                                [
                                    "id" => 1,
                                    "createdAt" => "2020-08-13",
                                    "initialValue" => "1200.00",
                                    "redeemed" => false,
                                    "expectedBalance" => "1638.06"
                                ],
                                [
                                    "id" => 2,
                                    "createdAt" => "2021-01-10",
                                    "initialValue" => "1500.00",
                                    "redeemed" => true,
                                    "expectedBalance" => "1700.00"
                                ]
                            ],
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "createdAt", type: "string"),
                                    new OA\Property(property: "initialValue", type: "string"),
                                    new OA\Property(property: "redeemed", type: "boolean"),
                                    new OA\Property(property: "expectedBalance", type: "string")
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 428, 
                description: "<p>Erro na operação, um JSON será retornado com as seguintes informações:</p><br>
                                <b>status</b>: Status da operação<br>
                                <b>message</b>: Mensagem de erro<br>",
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(
                            properties: [
                                new OA\Property(property: "status", type: "string", example: "Error"),
                                new OA\Property(property: "message", type: "string", example: "Atenção : Investimento não encontrado!"),
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function listByOwner(int $ownerId, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true) ?: $request->request->all();
            if (empty($ownerId) || $ownerId <= 0) {
                throw new \InvalidArgumentException('Id inválido!');
            }

            $owner = $this->ownerRepository->find($ownerId);
            if (!$owner) {
                throw new \InvalidArgumentException('Investidor não encontrado!');
            }

            $page = empty($data['page']) ? 1 : (int)$data['page'];
            $limit = empty($data['limit']) ? 100 : (int)$data['limit'];
            $offset = ($page - 1) * $limit;

            $total = (int) $this->investmentRepository->createQueryBuilder('i')
                ->select('COUNT(i.id)')
                ->andWhere('i.owner = :owner')
                ->setParameter('owner', $owner)
                ->getQuery()
                ->getSingleScalarResult();

            $investments = $this->investmentRepository->createQueryBuilder('i')
                ->andWhere('i.owner = :owner')
                ->setParameter('owner', $owner)
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->orderBy('i.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
            
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

            $this->emailService->sendEmail(
                $_ENV['EMAIL_TO'],
                'API de Investimentos',
                [
                    'subject' => 'API de Investimentos - Api/Owner',
                    'name' => 'Administrador',
                    'message' => 'O endpoint de "Visualização de Investimento de um Investidor" foi executado.'
                ]
            );

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
        } catch (\Exception $e) {
            return $this->json(['status' => 'Error','message' => 'Atenção : ' . $e->getMessage()], 428);
        }
    }
}
