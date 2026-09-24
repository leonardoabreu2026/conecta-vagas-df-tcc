<?php
declare(strict_types=1);
/**
 * DTO (Data Transfer Object) do perfil: transporta os dados de um formulário ou da
 * extração do currículo até o PerfilDAO::salvar(), com um getter para cada coluna.
 * tem() diz se um campo veio nos dados (campos ausentes não são sobrescritos).
 */
final class PerfilDTO {
    public function __construct(private array $d=[]){}
    public function getId():mixed{return $this->d['id']??null;} public function getUsuarioId():mixed{return $this->d['usuario_id']??null;}
    public function getTituloProfissional():mixed{return $this->d['titulo_profissional']??null;}
    public function getBio():mixed{return $this->d['bio']??null;} public function getDataNascimento():mixed{return $this->d['data_nascimento']??null;}
    public function getCidade():mixed{return $this->d['cidade']??null;} public function getUf():mixed{return $this->d['uf']??null;}
    public function getNomeFantasia():mixed{return $this->d['nome_fantasia']??null;} public function getCnpj():mixed{return $this->d['cnpj']??null;}
    public function getSetor():mixed{return $this->d['setor']??null;} public function getSite():mixed{return $this->d['site']??null;}
    public function getHabilidades():mixed{return $this->d['habilidades']??null;} public function getExperiencias():mixed{return $this->d['experiencias']??null;}
    public function getFormacao():mixed{return $this->d['formacao']??null;} public function getIdiomas():mixed{return $this->d['idiomas']??null;}
    public function getCursosComplementares():mixed{return $this->d['cursos_complementares']??null;}
    public function getInformacoesAdicionais():mixed{return $this->d['informacoes_adicionais']??null;}
    public function getCompetencias():mixed{return $this->d['competencias']??null;} public function getNivelExperiencia():mixed{return $this->d['nivel_experiencia']??'junior';}
    public function getDisponibilidade():mixed{return $this->d['disponibilidade']??null;} public function getObjetivo():mixed{return $this->d['objetivo']??null;}
    public function getFoto():mixed{return $this->d['foto']??null;} public function getPublico():mixed{return $this->d['publico']??1;}
    public function getAceiteLgpd():mixed{return $this->d['aceite_lgpd']??1;}
    public function getLinks():mixed{return $this->d['links']??null;} public function getCnh():mixed{return $this->d['cnh']??null;}
    public function getPretensaoSalarial():mixed{return $this->d['pretensao_salarial']??null;}
    /** Se o campo veio nos dados (campos novos só são gravados quando informados). */
    public function tem(string $campo):bool{return array_key_exists($campo,$this->d);}
}
