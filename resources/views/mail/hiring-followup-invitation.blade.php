<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="font-family: Figtree, Arial, sans-serif; color: #1e293b; line-height: 1.5;">
    @foreach (preg_split('/\n{2,}/', trim($bodyText)) as $paragraph)
        <p style="margin: 0 0 12px;">{!! preg_replace('~(https?://\S+)~', '<a href="$1" style="color:#632a7e;font-weight:600;">$1</a>', nl2br(e($paragraph))) !!}</p>
    @endforeach
    <p style="font-size:12px;color:#64748b;margin-top:24px;">
        Pesquisa de acompanhamento da Talents Gestão de Pessoas. O link é individual; não o encaminhe.
    </p>
</body>
</html>
