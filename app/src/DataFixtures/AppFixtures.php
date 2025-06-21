<?php

namespace App\DataFixtures;

use App\Entity\Users;
use App\Entity\Categories;
use App\Entity\Products;
use App\Entity\Orders;
use App\Entity\OrderItems;
use App\Entity\Addresses;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class AppFixtures extends Fixture
{
    private $hasher;
    private $slugger;

    public function __construct(UserPasswordHasherInterface $hasher, SluggerInterface $slugger)
    {
        $this->hasher = $hasher;
        $this->slugger = $slugger;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // --- USERS ---
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $user = new Users();
            $user->setEmail($faker->email)
                ->setFirstname($faker->firstName)
                ->setLastname($faker->lastName)
                ->setPassword($this->hasher->hashPassword($user, 'password'))
                ->setRoles(['ROLE_USER'])
                ->setCreatedAt(\DateTimeImmutable::createFromMutable(
                    $faker->dateTimeBetween('-1 year', 'now')
                    )
                );
            $manager->persist($user);
            $users[] = $user;

            // Addresses
            for ($j = 0; $j < rand(1, 2); $j++) {
                $address = new Addresses();
                $address->setFullname($user->getFirstname() . ' ' . $user->getLastname())
                    ->setAddress($faker->streetAddress)
                    ->setCity($faker->city)
                    ->setPostalCode($faker->postcode)
                    ->setCountry($faker->country)
                    ->setUsers($user);
                $manager->persist($address);
            }
        }

        // --- CATEGORIES ---
        $categories = [];
        $categoryNames = ['Laptops', 'Smartphones', 'Écrans', 'Périphériques', 'Composants'];
        foreach ($categoryNames as $name) {
            $category = new Categories();
            $category->setName($name)
                ->setSlug(strtolower($this->slugger->slug($name)));
            $manager->persist($category);
            $categories[] = $category;
        }

        // --- PRODUCTS ---
        $products = [];
        for ($i = 0; $i < 30; $i++) {
            $product = new Products();
            $name = $faker->words(3, true);
            $product->setName($name)
                ->setDescription($faker->paragraph)
                ->setPrice($faker->randomFloat(2, 50, 1500))
                ->setStock(rand(10, 100))
                ->setCreatedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeThisYear()))
                ->setCategories($faker->randomElement($categories));
            $manager->persist($product);
            $products[] = $product;
        }

        // --- ORDERS + ORDER ITEMS ---
        for ($i = 0; $i < 10; $i++) {
            $user = $faker->randomElement($users);
            $userAddresses = $user->getAddresses();
            if (count($userAddresses) === 0) {
                continue; // skip if no address
            }

            $order = new Orders();
            $order->setReference(uniqid('CMD'))
                ->setCreatedAt(
                \DateTimeImmutable::createFromMutable($faker->dateTimeThisYear()))
                ->setStatus('PAID')
                ->setUsers($user)
                ->setAddresses($faker->randomElement($userAddresses->toArray()));

            $total = 0;

            for ($j = 0; $j < rand(1, 4); $j++) {
                $product = $faker->randomElement($products);
                $qty = rand(1, 3);
                $price = $product->getPrice();

                $item = new OrderItems();
                $item->setOrders($order)
                    ->setProducts($product)
                    ->setQuantity($qty)
                    ->setPrice($price);
                $manager->persist($item);

                $total += $qty * $price;
            }

            $order->setTotal($total);
            $manager->persist($order);
        }

        $manager->flush();
    }
}
