<?php
namespace App\Controller;

use App\Form\FileUploadType;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class FileController extends AbstractController
{
    private $filesystem;

    public function __construct(FilesystemOperator $defaultStorage)
    {
        $this->filesystem = $defaultStorage;
    }

    #[Route('/upload', name: 'upload')]
    public function upload(Request $request): Response
    {
        $form = $this->createForm(FileUploadType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            if ($file) {
                $newFilename = uniqid().'.'.$file->guessExtension();
                $stream = fopen($file->getRealPath(), 'r+');
                $this->filesystem->writeStream($newFilename, $stream);
                fclose($stream);

                $this->addFlash('success', 'File uploaded successfully!');
            }

            return $this->redirectToRoute('upload');
        }

        return $this->render('file/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/download/{filename}', name: 'download')]
    public function download(string $filename): Response
    {
        $exists = $this->filesystem->fileExists($filename);

        if (!$exists) {
            throw $this->createNotFoundException('The file does not exist');
        }

        $fileStream = $this->filesystem->readStream($filename);
        $response = new Response(stream_get_contents($fileStream));
        fclose($fileStream);

        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );

        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
