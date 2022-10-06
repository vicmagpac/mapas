<?php

namespace EventImporter;

use DateTime;
use Exception;
use MapasCulturais\i;
use League\Csv\Reader;
use MapasCulturais\App;
use League\Csv\Statement;
use MapasCulturais\Entities\Event;
use MapasCulturais\Controllers\Space;

class Controller extends \MapasCulturais\Controller
{

   function GET_uploadFile()
   {
      $app = App::i();

      $request = $this->data;
      $file = $app->repo('File')->find($request['file']);
      $file_dir = $file->path;

      if (file_exists($file_dir)) {
         $data = $this->processCSV($file_dir);
      }else{
         throw new Exception("Arquivo CSV não existe. Erro ao processar");
      }
   }

   //Processa arquivos CSV
   public function processCSV(string $file_dir)
   {
      $app = App::i();

      $moduleConfig = $app->modules['EventImporter']->config;

      $stream = fopen($file_dir, 'r');

      $csv = Reader::createFromStream($stream);
      $csv->setDelimiter(";");
      $csv->setHeaderOffset(0);

      $stm = (new Statement());
      $data = $stm->process($csv);

      // Verificar se no csv existe as colunas minimas para cadastrar um evento "Colunas Obrigatorias"

      // Mapear as colunas 
      foreach ($data as $key => $value) {

         if(empty($value['NAME']) || $value['NAME'] == ''){
            $this->error("A coluna nome está vazia na linha {$key}");
         }

         if(empty($value['SHORT_DESCRIPTION']) || $value['SHORT_DESCRIPTION'] == ''){
            $this->error("A coluna descrição curta está vazia na linha {$key}");
         }

         if(empty($value['CLASSIFICATION']) || $value['CLASSIFICATION'] == ''){
            $this->error("A coluna classificação estária está vazia na linha {$key}");
         }

         if (!in_array($value['CLASSIFICATION'],$moduleConfig['rating_list'])) {
            $rating_str = implode(', ',$moduleConfig['rating_list']);
            $this->error("A classificação etária é inválida {$key}. As opções aceitas são --{$rating_str}--");
         }

         //Validação das linguagens
         $languages = explode(',', $value['LANGUAGE']);
         if (!$languages) {
            $this->error("Linguagem está vazia na linha {$key}");
         }

         //Tratamento da lista
         $languages_list = $app->getRegisteredTaxonomyBySlug('linguagem')->restrictedTerms;

         foreach ($languages as $language) {
            $_language = mb_strtolower($language);

            if (!in_array($_language, array_keys($languages_list))) {
               $this->error("linguagem{$_language} não existe");
            }
         }

         //Validação do projeto
         $collum_proj = 'id';
         if (!is_numeric($value['PROJECT'])) {
            $collum_proj = 'name';
         }

         if (!$projects = $app->repo('Project')->findBy([$collum_proj => $value['PROJECT']])) {
            $this->error("O Projeto Não está cadastrado na linha {$key}");
         }

         if ($collum_proj == 'name') {
            if (count($projects) > 1){
               $this->error("Existem mais de um projeto com o nome {$value['PROJECT']}, Para proseguir informe o ID do projeto que quer associar ao evento");
            }
         }

         //Validação do agente responsavel 
         if (!$agent = $app->repo('Agent')->find($value['OWNER'])) {
            $this->error("O a gente não esta cadastrado");
         }

         //Validação do espaço
         $collum_spa = 'id';
         if (!is_numeric($value['SPACE'])) {
            $collum_spa = 'name';
         }

         if (!$spaces = $app->repo('Space')->findBy([$collum_spa => $value['SPACE']])) {
            $this->error("O espaço não esta cadastrado");
         }

         if ($collum_spa == 'name') {
            if (count($spaces) > 1) {
               $this->error("Existem mais de um espaço com o nome {$value['SPACE']}, Para proseguir informe o ID do espaço que quer associar ao evento");
            }
         }

         //Verificação da frequencia
         if(empty($value['FREQUENCY']) || $value['FREQUENCY'] == ''){
            $this->error("A coluna Frequência está vazia na linha {$key}");
         }

         if (!in_array($value['FREQUENCY'],$moduleConfig['frequence_list'])) {
            $frequence_str = implode(', ',$moduleConfig['frequence_list']);
            $this->error("A Frequência é inválida na linha {$key}. As opções aceitas são --{$frequence_str}-- ");
         }
         
         //criação do enveto
         $event = new Event();
         $event->name = $value['NAME'];
         $event->shortDescription = $value['SHORT_DESCRIPTION'];
         $event->classificacaoEtaria = $value['CLASSIFICATION'];
         $event->owner = $agent;
         $event->terms['linguagem'] = $languages;
         $event->projectId = $projects[0]->id;
         $event->save(true);

         $this->createOcurrency($event, $value, $key);

      }
   }


   public function createOcurrency($event, $value, $key)
   {
      $app = App::i();

      $_starts_at = (new DateTime('now'))->format("Y-m-d")." ".$value['STARTS_AT'];
      $_ends_at = (new DateTime('now'))->format("Y-m-d")." ".$value['ENDS_AT'];
      
      $starts_at = new DateTime($_starts_at);
      $ends_at = new DateTime($_ends_at);

      if(empty($value['STARTS_AT']) || $value['STARTS_AT'] == ''){
         $this->error("A coluna Hora inícial está vazia na linha {$key}");
      }   

      if($starts_at->format("H:i") != $value['STARTS_AT']){
         $this->error("A coluna Hora final é inválida na linha {$key}");
      }

      if(empty($value['ENDS_AT']) || $value['ENDS_AT'] == ''){
         $this->error("A coluna Hora final está vazia na linha {$key}");
      }

      if($ends_at->format("H:i") != $value['ENDS_AT']){
         $this->error("A coluna Hora final é inválida na linha {$key}");
      }
  
      switch (mb_strtolower($value['FREQUENCY'])) {
         case i::__('diariamente'):
         case i::__('todos os dias'):
         case i::__('diario'):
         case i::__('daily'):
            $result = function ($value, $key, $app) {
                $starts_on = new DateTime($value['STARTS_ON']);
                $ends_on = new DateTime($value['ENDS_ON']);

                if (empty($value['STARTS_ON']) || $value['STARTS_ON'] == "") {
                    $this->error("A Coluna Data inícial Está vazia na linha {$key}");
                }

                if (empty($value['ENDS_ON']) || $value['ENDS_ON'] == "") {
                    $this->error("A Coluna Data Final Está vazia na linha {$key}");
                }

                if ($starts_on->format("d/m/Y") != $value['STARTS_ON']) {
                    $this->error("O formato da Data inícial é inválido na linha {$key}. O formato esperado é YYYY/MM/DD");
                }

                if ($ends_on->format("d/m/Y") != $value['ENDS_ON']) {
                    $this->error("O formato da Data Final é inválido na linha {$key}. O formato esperado é YYYY/MM/DD");
                }

                return ['starts_on' => $starts_on, 'ends_on' => $ends_on];
            };
            break;
         case i::__('semanal'):
         case i::__('toda semana'):
         case i::__('weekly'):
            $result = function ($value, $key, $app) {
               $moduleConfig = $app->modules['EventImporter']->config;

               $week_days = $moduleConfig['week_days'];
               $days_list_positive = $moduleConfig['days_list_positive'];

               $days = [];
               foreach ($week_days as $day) {
                  if (in_array($value[$day], $days_list_positive)) {
                     $days[] = $day;
                  }
               }

               return $days;
            };
            break;
         case i::__('uma vez'):
         case i::__('once'):
            $result = function ($value, $key, $app) {
               $starts_on = new DateTime($value['STARTS_ON']);

               if (empty($value['STARTS_ON']) || $value['STARTS_ON'] == "") {
                  $this->error("A Coluna Data inícial Está vazia na linha {$key}");
               }

               if ($starts_on->format("d/m/Y") != $value['STARTS_ON']) {
                  $this->error("O formato da Data inícial é inválido na linha {$key}. O formato esperado é YYYY/MM/DD");
               }

               return ['starts_on' => $starts_on];
            };
            break;
      }
   }

   public function error($message)
   {
      throw new Exception(i::__($message));
   }
  
}
