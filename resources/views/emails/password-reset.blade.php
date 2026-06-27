<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f8; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background-color:#0ea5a4; padding:20px 24px;">
                            <span style="color:#ffffff; font-size:20px; font-weight:bold;">MyPharma</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px;">
                            <h1 style="font-size:18px; margin:0 0 12px;">Réinitialisation de votre mot de passe</h1>
                            <p style="font-size:14px; line-height:1.6; margin:0 0 16px;">
                                Bonjour,<br>
                                Une demande de réinitialisation du mot de passe a été effectuée pour le compte
                                <strong>{{ $email }}</strong>. Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe.
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                                <tr>
                                    <td align="center" style="border-radius:8px; background-color:#0ea5a4;">
                                        <a href="{{ $resetUrl }}" target="_blank"
                                           style="display:inline-block; padding:12px 24px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:8px;">
                                            Réinitialiser mon mot de passe
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="font-size:12px; line-height:1.6; color:#6b7280; margin:0 0 8px;">
                                Ce lien est valable un temps limité. Si vous n'êtes pas à l'origine de cette demande,
                                vous pouvez ignorer cet email : votre mot de passe restera inchangé.
                            </p>
                            <p style="font-size:12px; line-height:1.6; color:#6b7280; margin:16px 0 0; word-break:break-all;">
                                Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
                                <a href="{{ $resetUrl }}" style="color:#0ea5a4;">{{ $resetUrl }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px; background-color:#f9fafb; border-top:1px solid #e5e7eb;">
                            <p style="font-size:11px; color:#9ca3af; margin:0;">© {{ date('Y') }} MyPharma. Tous droits réservés.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
