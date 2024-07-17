<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DownloadController extends AbstractController
{
    private $minioClient;
    private $bucketName;

    public function __construct(ParameterBagInterface $params)
    {
        $this->bucketName = $params->get('minio.bucketName');

        $this->minioClient = new \Aws\S3\S3Client([
            'version' => 'latest',
            'region'  => $params->get('minio.region'),
            'endpoint' => $params->get('minio.endpoint'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key'    => $params->get('minio.key'),
                'secret' => $params->get('minio.secret'),
            ],
        ]);
    }

    /**
     * @Route("/download", name="download_list")
     */
    public function listFiles(): Response
    {
        try {
            $result = $this->minioClient->listObjectsV2([
                'Bucket' => $this->bucketName,
            ]);

            $files = [];
            foreach ($result['Contents'] as $object) {
                $files[] = $object['Key'];
            }

            return $this->render('download.html.twig', [
                'files' => $files,
            ]);
        } catch (\Exception $e) {
            return new Response('Error retrieving files: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @Route("/download/{filename}", name="download_file")
     */
    public function downloadFile(string $filename): Response
    {
        try {
            $result = $this->minioClient->getObject([
                'Bucket' => $this->bucketName,
                'Key'    => urldecode($filename),
            ]);

            $response = new Response($result['Body'], 200, [
                'Content-Type' => $result['ContentType'],
                'Content-Disposition' => 'attachment; filename="' . urldecode($filename) . '"',
            ]);

            return $response;
        } catch (\Exception $e) {
            return new Response('Error downloading file: ' . $e->getMessage(), 500);
        }
    }

}
