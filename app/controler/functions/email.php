<?php
/**
 * Fonctions de gestion des emails
 * 
 * @author Duplicator Team
 * @version 1.0
 */

require_once __DIR__ . '/../conf.php';
require_once __DIR__ . '/database.php';

/**
 * Ajoute un email à la liste de diffusion et envoie un email de souscription
 * 
 * @param string $email L'adresse email à ajouter
 * @return string Message de succès ou d'erreur
 */
function add_email_to_mailing_list($email)
{
    // Envoyer l'email de souscription
    $to = "duplicator-subscribe@lists.riseup.net";
    $subject = "subscribe";
    $message = "not_bb";
    $passage_ligne = "\r\n";
    $headers = "From: <".$email.">".$passage_ligne;
    
    // Envoyer l'email (on ne fait pas var_dump en production)
    $mail_sent = mail($to, $subject, $message, $headers);
    
    // Ajouter l'email à la base de données
    $db = pdo_connect();
    $query = $db->prepare('INSERT into email (email) VALUES(:email)');
    $query->bindParam(':email', $email);
    
    try {
        $query->execute();
        $result = '<div class="alert alert-success">
             <strong>Succès!</strong> Votre email a bien été ajouté
        </div>';
        return $result;
    } catch (PDOException $e) {
        $result = '<div class="alert alert-danger">
            <strong>Danger!</strong> Une erreur s\'est produite lors de l\'ajout de votre email.
        </div>';
        return $result;
    }
}


