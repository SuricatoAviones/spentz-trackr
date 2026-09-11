export function getCookie(name: string): string {
    const match = document.cookie.match(
        new RegExp(
            '(?:^|; )' +
                name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') +
                '=([^;]*)',
        ),
    );

    return match ? decodeURIComponent(match[2]) : '';
}

export function postJson(
    url: string,
    payload: Record<string, unknown>,
): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
        },
        body: JSON.stringify(payload),
    });
}
