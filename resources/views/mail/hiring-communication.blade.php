<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="font-family: Figtree, Arial, sans-serif; color: #1e293b; line-height: 1.5;">
    @foreach (preg_split('/\n{2,}/', trim($bodyText)) as $paragraph)
        <p style="margin: 0 0 12px;">{!! nl2br(e($paragraph)) !!}</p>
    @endforeach
    <p style="font-size:12px;color:#64748b;margin-top:24px;">
        Mensagem enviada pela Talents Gestão de Pessoas sobre o processo de contratação da sua empresa.
    </p>
</body>
</html>
