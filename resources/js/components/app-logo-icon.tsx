import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    return (
        <img
            {...props}
            src="/images/logo.png"
            alt="Spentz Trackr"
            className={`rounded-lg object-contain ${props.className ?? ''}`}
        />
    );
}
