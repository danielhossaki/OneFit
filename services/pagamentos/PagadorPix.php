<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;
require_once __DIR__.'/PagamentoException.php';

final readonly class PagadorPix implements \JsonSerializable
{
    private function __construct(private string $email, public string $ambiente, private bool $fixture=false) {}
    public static function testing(bool $fixtureOficial=false): self
    {
        return new self('test_user_br@testuser.com','testing',$fixtureOficial);
    }
    public static function cadastro(#[\SensitiveParameter] string $email): self
    {
        if(strlen($email)>150||!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new PagamentoException();
        return new self($email,'production');
    }
    /** Sensitive API boundary only. Never log the returned array. */
    public function paraApi(): array
    {
        return $this->fixture?['email'=>$this->email,'first_name'=>'APRO']:['email'=>$this->email];
    }
    public function fixtureOficial(): bool {return $this->fixture;}
    public function __debugInfo(): array {return ['ambiente'=>$this->ambiente,'dados'=>'omitidos'];}
    public function jsonSerialize(): mixed {return $this->__debugInfo();}
    public function __serialize(): array {throw new PagamentoException();}
}
