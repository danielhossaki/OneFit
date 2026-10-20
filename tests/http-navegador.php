<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Cliente HTTP mínimo dos testes de ponta a ponta (um "navegador" por pessoa,
// com cookies próprios). Usado só contra a cópia sintética em 127.0.0.1.

final class Navegador
{
    private array $cookies = [];

    public function __construct(private string $base) {}

    public function req(string $metodo, string $caminho, array $post = []): array
    {
        $ch = curl_init($this->base . $caminho);
        $cabecalhos = [];
        if ($this->cookies) {
            $pares = [];
            foreach ($this->cookies as $nome => $valor) {
                $pares[] = $nome . '=' . $valor;
            }
            $cabecalhos[] = 'Cookie: ' . implode('; ', $pares);
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $cabecalhos,
        ]);
        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            // Com arquivo (CURLFile) o corpo vai como multipart/form-data, como no navegador.
            $multipart = (bool) array_filter($post, static fn($valor): bool => $valor instanceof CURLFile);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $post : http_build_query($post));
        } elseif ($metodo !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo);
        }
        $resposta = curl_exec($ch);
        if ($resposta === false) {
            throw new RuntimeException('HTTP falhou: ' . curl_error($ch));
        }
        $tamanho = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headers = substr($resposta, 0, $tamanho);
        preg_match_all('/^Set-Cookie:\s*([^=;\s]+)=([^;\r\n]*)/mi', $headers, $novos, PREG_SET_ORDER);
        foreach ($novos as [, $nome, $valor]) {
            if ($valor === '' || $valor === 'deleted') {
                unset($this->cookies[$nome]);
            } else {
                $this->cookies[$nome] = $valor;
            }
        }
        preg_match('/^Location:\s*(\S+)/mi', $headers, $local);
        return ['status' => $status, 'location' => $local[1] ?? '', 'body' => substr($resposta, $tamanho)];
    }

    public function entrar(string $email, string $senha = 'Fixture-only-123!'): array
    {
        $this->req('GET', 'pages/login/login.php');
        return $this->req('POST', 'pages/login/login.php', ['email' => $email, 'password' => $senha]);
    }

    public function csrf(): string
    {
        $painel = $this->req('GET', 'pages/dashboard/dashboard.php');
        if (!preg_match('/const BO_CSRF_TOKEN = "([a-f0-9]{64})"/', $painel['body'], $m)) {
            throw new RuntimeException('Token CSRF não encontrado no painel.');
        }
        return $m[1];
    }
}
