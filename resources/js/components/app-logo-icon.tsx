import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    return (
        <img
            {...props}
            src="/images/logo.png"
            alt="Spent Trackr"
            className={`rounded-lg object-contain ${props.className ?? ''}`}
        />
    );
}
