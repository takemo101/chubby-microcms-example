<?php

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Exception\RequestException;
use Takemo101\Chubby\Http;
use Takemo101\Chubby\ApplicationOption;
use Takemo101\Chubby\Bootstrap\Provider\ClosureProvider;
use Takemo101\Chubby\Http\Renderer\HtmlRenderer;
use Takemo101\Chubby\Support\ApplicationPath;
use Latte\Engine as Latte;
use Latte\Loaders\FileLoader;
use Microcms\Client as MicroCMSClient;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Slim\Interfaces\RouteParserInterface;

// このアプリケーションでは、ChubbyというSlim4をラップしたパッケージを利用しています。
// Chubbyでは、Slim4 + PHP-DI（Slim PHP-DI Bridge） + Slim-PSR7 + symfony/console などをラップしています。

/**
 * Latteでのテンプレートレンダリングの支援をするクラス
 */
class LatteRenderer
{
    /**
     * constructor
     *
     * @param Latte $latte
     * @param mixed[] $shared
     */
    public function __construct(
        private readonly Latte $latte,
        private array $shared = [],
    ) {
        //
    }

    /**
     * 共有変数を設定する
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setShared(string $key, mixed $value): self
    {
        $this->shared[$key] = $value;

        return $this;
    }

    /**
     * テンプレートをレンダリングして
     * HtmlRendererを取得する
     *
     * @param string $template
     * @param mixed[] $params
     * @return HtmlRenderer
     */
    public function template(
        string $template,
        array $params = [],
    ): HtmlRenderer {
        return new HtmlRenderer(
            $this->latte->renderToString(
                $template,
                [
                    ...$this->shared,
                    ...$params,
                ],
            ),
        );
    }
}

// Webだけを利用する場合は
// Http::createSimple()で
// 簡易的にHttpインスタンスを構築できる
$http = Http::createSimple(
    ApplicationOption::from(
        // プロジェクトのルートを指定
        basePath: dirname(__DIR__),
    ),
);

// プロバイダーによって
// アプリケーション起動時の処理や
// サービスコンテナへの登録を行う（サービスコンテナはPHP-DIを利用している）
$http->addProvider(
    new ClosureProvider(
        fn () => [
            // Latteテンプレートエンジンの構築を定義
            Latte::class => fn (
                ApplicationPath $path,
                RouteParserInterface $routeParser,
            ) => (new Latte())
                // テンプレート置くディレクトリを指定
                ->setLoader(
                    new FileLoader(
                        // ApplicationPathは、Chubbyのアプリケーションのパスを取得するヘルパです
                        $path->getBasePath('templates'),
                    ),
                )
                // テンプレートのキャッシュを保存するディレクトリを指定
                ->setTempDirectory(
                    $path->getBasePath('storage/templates'),
                )
                // テンプレート内でroute関数を利用できるようにする
                ->addFunction(
                    'route',
                    fn (string $name, array $params = []) => $routeParser->urlFor($name, $params),
                )
                // テンプレートの自動リフレッシュを有効にする
                ->setAutoRefresh(true),

            // MicroCMS SDKの構築を定義
            MicroCMSClient::class => fn () => new MicroCMSClient(
                // 環境変数からMicroCMSのサービスドメインとAPIキーを取得する
                // Chubbyでは、envヘルパ関数から環境変数を取得できる
                serviceDomain: env('MICROCMS_SERVICE_DOMAIN'),
                apiKey: env('MICROCMS_API_KEY'),
            ),
        ],
    ),
);

// トップページ
$http->get('/', function (
    LatteRenderer $renderer,
    MicroCMSClient $client,
) {
    // MicroCMSのSDKを利用して、APIから記事一覧を取得する
    $list = $client->list('blogs');

    $blogs = $list->contents;

    // 通常のSlimでは、ResponseInterfaceを戻り値として返す必要があるが
    // 今回利用しているChubbyでは、ResponseInterface以外のオブジェクトなどを戻り値として返すことができる
    return $renderer->template(
        'index.latte',
        compact('blogs'),
    );
})
    // ルートに名付けすることで
    // この名前でURLパスを参照できるようになる
    ->setName('home');

// カテゴリー別の記事一覧ページ
$http->get('/category/{id}', function (
    ServerRequestInterface $request,
    LatteRenderer $renderer,
    MicroCMSClient $client,
    string $id,
) {
    try {
        $category = $client->get('categories', $id);
    } catch (RequestException $e) {
        // 一旦RequestExceptionが発生した場合は、404エラーとして扱うが
        // 本来は、もう少し詳細なエラーハンドリングを行うべき
        throw new HttpNotFoundException($request, $e->getMessage(), $e);
    }

    $list = $client->list('blogs', [
        'filters' => "category[equals]{$id}",
    ]);

    $blogs = $list->contents;

    return $renderer->template(
        'category.latte',
        compact('category', 'blogs'),
    );
})->setName('category');

$http->group('/blog/{id}', function (
    RouteCollectorProxyInterface $http
) {
    // 記事詳細ページ
    $http->get('/', function (
        ServerRequestInterface $request,
        LatteRenderer $renderer,
        MicroCMSClient $client,
        string $id,
    ) {
        try {
            $blog = $client->get('blogs', $id);
        } catch (RequestException $e) {
            throw new HttpNotFoundException($request, $e->getMessage(), $e);
        }

        // 下書き中の記事かどうか
        $isDraft = false;

        return $renderer->template(
            'detail.latte',
            compact('blog', 'isDraft'),
        );
    })->setName('blog.detail');

    // 下書き中の記事詳細確認ページ
    $http->get('/draft', function (
        ServerRequestInterface $request,
        LatteRenderer $renderer,
        MicroCMSClient $client,
        string $id,
    ) {
        try {
            $blog = $client->get('blogs', $id, [
                'draftKey' => $request->getQueryParams()['key'] ?? null,
            ]);
        } catch (RequestException $e) {
            throw new HttpNotFoundException($request, $e->getMessage(), $e);
        }

        // 下書き中の記事かどうか
        $isDraft = true;

        return $renderer->template(
            'detail.latte',
            compact('blog', 'isDraft'),
        );
    })->setName('blog.draft');
});

// Slimでは、Closure（callable値）をミドルウェアとして登録することができる
$http->add(
    /**
     * ミドルウェアでページで共有する値を設定する
     * ここでは、トップメニューを表示するためのカテゴリーリストを共有値として設定している
     *
     * Closureでは $this によってサービスコンテナを利用できる
     */
    function (
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ) {
        /** @var ContainerInterface $this */

        /** @var LatteRenderer */
        $renderer = $this->get(LatteRenderer::class);

        /** @var MicroCMSClient */
        $client = $this->get(MicroCMSClient::class);

        $renderer->setShared(
            'categories',
            $client->list('categories')->contents,
        );

        return $handler->handle($request);
    }
);

$http->run();
