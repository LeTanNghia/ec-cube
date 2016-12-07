<?php

namespace Eccube\Form\Type\Shopping;

use Eccube\Application;
use Eccube\Repository\DeliveryRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\PaymentRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class OrderType extends AbstractType
{
    /** @var  Application */
    protected $app;

    /** @var  OrderRepository */
    protected $orderRepository;

    /** @var  DeliveryRepository */
    protected $deliveryRepository;

    /** @var  PaymentRepository */
    protected $paymentRepository;

    public function __construct(
        Application $app,
        OrderRepository $orderRepository,
        DeliveryRepository $deliveryRepository
    ) {
        $this->app = $app;
        $this->orderRepository = $orderRepository;
        $this->deliveryRepository = $deliveryRepository;
        $this->paymentRepository = $app['eccube.repository.payment'];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'message',
                'textarea',
                array(
                    'required' => false,
                    'constraints' => array(
                        new Length(array('min' => 0, 'max' => 3000)),
                    ),
                )
            )
            ->add(
                'Shippings',
                'collection',
                array(
                    'type' => '_shopping_shipping',
                )
            );

        // 支払い方法のプルダウンを生成
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                $Order = $event->getData();
                $OrderDetails = $Order->getOrderDetails();

                // 受注明細に含まれる商品種別を抽出.
                $ProductTypes = array();
                foreach ($OrderDetails as $OrderDetail) {
                    $ProductClass = $OrderDetail->getProductClass();
                    if (is_null($ProductClass)) {
                        // 商品明細のみ対象とする. 送料明細等はスキップする.
                        continue;
                    }
                    $ProductType = $ProductClass->getProductType();
                    $ProductTypes[$ProductType->getId()] = $ProductType;
                }

                // 商品種別に紐づく配送業者を抽出
                $Deliveries = $this->deliveryRepository->getDeliveries($ProductTypes);
                // 利用可能な支払い方法を抽出.
                $Payments = $this->paymentRepository->findAllowedPayments($Deliveries, true);

                $form = $event->getForm();
                $form->add(
                    'Payment',
                    'entity',
                    array(
                        'class' => 'Eccube\Entity\Payment',
                        'property' => 'method',
                        'expanded' => true,
                        'constraints' => array(
                            new NotBlank(),
                        ),
                        'choices' => $Payments,
                    )
                );
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'Eccube\Entity\Order',
            )
        );
    }

    public function getName()
    {
        return '_shopping_order';
    }
}