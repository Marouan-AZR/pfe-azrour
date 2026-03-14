<?php

namespace App\Form;

use App\Entity\StockExit;
use App\Entity\StockItem;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockExitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('stockItem', EntityType::class, [
                'class' => StockItem::class,
                'choice_label' => fn(StockItem $item) => sprintf('%s - %s (%.3f T disponibles)', 
                    $item->getClient()->getCompanyName(),
                    $item->getProductName(),
                    $item->getRemainingQuantity()
                ),
                'label' => 'Article en stock',
                'placeholder' => 'Sélectionner un article',
                'query_builder' => fn($repo) => $repo->createQueryBuilder('s')
                    ->join('s.client', 'c')
                    ->orderBy('c.companyName', 'ASC')
                    ->addOrderBy('s.productName', 'ASC'),
                'choice_attr' => fn(StockItem $item) => [
                    'data-max' => $item->getRemainingQuantity(),
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('quantityTons', NumberType::class, [
                'label' => 'Quantité à sortir (tonnes)',
                'scale' => 3,
                'html5' => true,
                'attr' => ['step' => '0.001', 'min' => '0.001', 'placeholder' => '0.000'],
            ])
            ->add('destination', TextType::class, [
                'label' => 'Destination',
                'required' => false,
                'attr' => ['placeholder' => 'Adresse de livraison'],
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
            ->add('chauffeur', TextType::class, [
                'label' => 'Chauffeur',
                'required' => false,
                'attr' => ['placeholder' => 'Nom du chauffeur'],
            ])
            ->add('observations', TextareaType::class, [
                'label' => 'Observations',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Remarques ou observations...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StockExit::class,
        ]);
    }
}
