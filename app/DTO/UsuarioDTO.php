<?php
declare(strict_types=1);
/**
 * DTO (Data Transfer Object) do usuário: transporta os dados do cadastro até o
 * UsuarioDAO::cadastrar(). Padrões: tipo 'candidato' e conta ativa.
 */
final class UsuarioDTO {
    public function __construct(private array $d=[]){}
    public function getId(): mixed{return $this->d['id']??null;}
    public function getNome(): mixed{return $this->d['nome']??null;}
    public function getEmail(): mixed{return $this->d['email']??null;}
    public function getSenha(): mixed{return $this->d['senha']??null;}
    public function getTipo(): mixed{return $this->d['tipo']??'candidato';}
    public function getTelefone(): mixed{return $this->d['telefone']??null;}
    public function getAtivo(): mixed{return $this->d['ativo']??1;}
}
