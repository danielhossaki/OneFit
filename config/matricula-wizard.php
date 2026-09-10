<?php

declare(strict_types=1);

/** Session-only enrollment draft. Never stores passwords or writes to the database. */
final class MatriculaWizard
{
    public static function novo(array &$session): array
    {
        unset($session['matricula_plano_pendente'], $session['matricula_retornar']);
        return $session['matricula_wizard'] = [
            'id' => bin2hex(random_bytes(16)),
            'usuario' => (int)($session['id_usuario'] ?? 0),
            'validado' => 0,
            'etapa' => 1,
            'dados' => []
        ];
    }
    public static function conferir(array $session, array $input): array
    {
        $w = $session['matricula_wizard'] ?? null;
        if (
            !$w || $w['usuario'] !== (int)($session['id_usuario'] ?? 0)
            || !is_string($input['fluxo'] ?? null) || !hash_equals($w['id'], $input['fluxo'])
            || strlen($session['csrf_token'] ?? '') !== 64 || !is_string($input['csrf_token'] ?? null) || !hash_equals($session['csrf_token'], $input['csrf_token'])
        ) throw new RuntimeException('Solicitação inválida.');
        return $w;
    }
    public static function avancar(array &$session, array $input, callable $plano, callable $cidade): int
    {
        $w = self::conferir($session, $input);
        $step = (int)($input['etapa'] ?? 0);
        if (isset($w['referencia'])) throw new RuntimeException('Retome o pagamento já iniciado.');
        if ($step < 1 || $step > 3 || $step > $w['validado'] + 1) throw new RuntimeException('Conclua as etapas anteriores.');
        $fields = [1 => ['nome', 'cpf', 'nascimento', 'genero', 'telefone', 'email'], 2 => ['cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'estado'], 3 => ['id_plano']];
        foreach ($fields[$step] as $field) {
            $v = $input[$field] ?? '';
            if (!is_string($v) || strlen($v) > 255) throw new RuntimeException('Confira os campos desta etapa.');
            $w['dados'][$field] = trim($v);
        }
        $w['validado'] = $step - 1;
        $w['etapa'] = $step;
        unset($w['confirmado']);
        $session['matricula_wizard'] = $w;
        $d = $w['dados'];
        $valid = false;
        if ($step === 1) {
            $cpf = preg_replace('/\D/', '', $d['cpf']);
            $cpfOk = preg_match('/^\d{11}$/D', $cpf) && !preg_match('/^(\d)\1{10}$/D', $cpf);
            if ($cpfOk) for ($n = 9; $n < 11; $n++) {
                $sum = 0;
                for ($i = 0; $i < $n; $i++) $sum += (int)$cpf[$i] * (($n + 1) - $i);
                if ((($sum * 10) % 11) % 10 !== (int)$cpf[$n]) $cpfOk = false;
            }
            $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $d['nascimento']);
            $today = new DateTimeImmutable('today');
            $valid = strlen($d['nome']) >= 2 && $cpfOk && $birth && $birth->format('Y-m-d') === $d['nascimento'] && $birth <= $today->modify('-12 years') && $birth >= $today->modify('-120 years')
                && in_array($d['genero'], ['masculino', 'feminino', 'outro'], true) && filter_var($d['email'], FILTER_VALIDATE_EMAIL)
                && preg_match('/^\d{10,11}$/D', preg_replace('/\D/', '', $d['telefone']));
            if (!$w['usuario']) $valid = $valid && is_string($input['password'] ?? null) && strlen($input['password']) >= 8 && $input['password'] === ($input['confirmar_senha'] ?? null);
        } elseif ($step === 2) {
            $valid = $d['endereco'] !== '' && $d['numero'] !== '' && $d['bairro'] !== '' && $d['cidade'] !== '' && preg_match('/^\d{8}$/D', preg_replace('/\D/', '', $d['cep']))
                && preg_match('/^(AC|AL|AP|AM|BA|CE|DF|ES|GO|MA|MT|MS|MG|PA|PB|PR|PE|PI|RJ|RN|RS|RO|RR|SC|SP|SE|TO)$/D', $d['estado']) && $cidade($d['estado'], $d['cidade']);
        } else {
            $valid = ctype_digit($d['id_plano']) && (int)$d['id_plano'] > 0 && $plano((int)$d['id_plano']);
        }
        if (!$valid) throw new RuntimeException('Confira os campos desta etapa.');
        $w['validado'] = $step;
        $w['etapa'] = $step + 1;
        $session['matricula_wizard'] = $w;
        return $step + 1;
    }
    public static function confirmar(array &$session, array $input): void
    {
        $w = self::conferir($session, $input);
        if ((int)($w['validado'] ?? 0) !== 3 || empty($input['termos']) || ((int)($input['id_plano'] ?? 0) !== (int)($w['dados']['id_plano'] ?? 0))) throw new RuntimeException('Conclua as etapas anteriores.');
        if (!$w['usuario']) foreach ($w['dados'] as $field => $value) {
            if (!is_string($input[$field] ?? null) || trim($input[$field]) !== $value) throw new RuntimeException('Valide novamente os dados alterados.');
        }
        $session['matricula_wizard']['confirmado'] = true;
    }
}
