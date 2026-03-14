<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\ColdRoom;
use App\Entity\StockEntry;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'companyName',
                'label' => 'Client',
                'placeholder' => 'Sélectionner un client',
                'query_builder' => fn($repo) => $repo->createQueryBuilder('c')
                    ->where('c.isActive = true')
                    ->orderBy('c.companyName', 'ASC'),
                'attr' => ['class' => 'form-select'],
            ])
            ->add('cdLotClient', TextType::class, [
                'label' => 'CD Lot Client',
                'required' => false,
                'attr' => ['placeholder' => 'Code lot du client'],
            ])
            ->add('coldRoom', EntityType::class, [
                'class' => ColdRoom::class,
                'choice_label' => fn(ColdRoom $room) => sprintf('%s (%.1f%% occupé)', 
                    $room->getName(), 
                    $room->getOccupancyRate()
                ),
                'label' => 'Chambre froide',
                'placeholder' => 'Sélectionner une chambre',
                'query_builder' => fn($repo) => $repo->createQueryBuilder('c')
                    ->where('c.isActive = true')
                    ->orderBy('c.name', 'ASC'),
                'attr' => ['class' => 'form-select'],
            ])
            ->add('productName', TextType::class, [
                'label' => 'Nom du produit',
                'attr' => ['placeholder' => 'Ex: Poulpe, Sardine, Crevette...'],
            ])
            ->add('famille', ChoiceType::class, [
                'label' => 'Famille',
                'required' => false,
                'placeholder' => 'Sélectionner une famille',
                'choices' => [
                    'Céphalopodes' => 'cephalopodes',
                    'Poissons pélagiques' => 'pelagiques',
                    'Poissons blancs' => 'blancs',
                    'Crustacés' => 'crustaces',
                    'Mollusques' => 'mollusques',
                    'Autres' => 'autres',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('qualite', ChoiceType::class, [
                'label' => 'Qualité',
                'required' => false,
                'placeholder' => 'Sélectionner une qualité',
                'choices' => [
                    'A - Premium' => 'A',
                    'B - Standard' => 'B',
                    'C - Économique' => 'C',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('moule', TextType::class, [
                'label' => 'Moule / Calibre',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: T1, T2, 40/60, 60/80...'],
            ])
            ->add('nombreCartons', IntegerType::class, [
                'label' => 'Nombre de cartons',
                'required' => false,
                'attr' => ['min' => 1, 'placeholder' => 'Nombre de cartons'],
            ])
            ->add('quantityTons', NumberType::class, [
                'label' => 'Poids brut (tonnes)',
                'scale' => 3,
                'html5' => true,
                'attr' => ['step' => '0.001', 'min' => '0.001', 'placeholder' => '0.000'],
            ])
            ->add('poidsNet', NumberType::class, [
                'label' => 'Poids net (tonnes)',
                'required' => false,
                'scale' => 3,
                'html5' => true,
                'attr' => ['step' => '0.001', 'min' => '0.001', 'placeholder' => '0.000'],
            ])
            ->add('rayon', TextType::class, [
                'label' => 'Rayon',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: R1, R2...'],
            ])
            ->add('codePalette', TextType::class, [
                'label' => 'Code Palette',
                'required' => false,
                'attr' => ['placeholder' => 'Code unique de la palette'],
            ])
            ->add('codeRack', TextType::class, [
                'label' => 'Code Rack',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 1G, 5D...'],
            ])
            ->add('transporteur', TextType::class, [
                'label' => 'Transporteur',
                'required' => false,
                'attr' => ['placeholder' => 'Nom du transporteur'],
            ])
            ->add('immatriculation', TextType::class, [
                'label' => 'Immatriculation',
                'required' => false,
                'attr' => ['placeholder' => 'Plaque du véhicule'],
            ])
            ->add('temperature', NumberType::class, [
                'label' => 'Température (°C)',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.1', 'placeholder' => '-18.0'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StockEntry::class,
        ]);
    }
}
