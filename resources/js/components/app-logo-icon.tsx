import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="d2d-title d2d-desc">
            <title id="d2d-title">Dirty2Data abstract data cleaning logo</title>
            <desc id="d2d-desc">A modern logo showing messy data particles transforming into a clean table and analytics chart.</desc>
            <defs>
                <linearGradient id="navyGradient" x1="106" y1="116" x2="240" y2="386" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stopColor="#061B3A" />
                    <stop offset="1" stopColor="#0B63CE" />
                </linearGradient>
                <linearGradient id="cleanGradient" x1="250" y1="104" x2="421" y2="405" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stopColor="#09D7C3" />
                    <stop offset="0.55" stopColor="#0BA7D6" />
                    <stop offset="1" stopColor="#2563EB" />
                </linearGradient>
                <linearGradient id="flowGradient" x1="202" y1="88" x2="308" y2="423" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stopColor="#67E8F9" />
                    <stop offset="0.48" stopColor="#14B8A6" />
                    <stop offset="1" stopColor="#2563EB" />
                </linearGradient>
                <linearGradient id="chartGradient" x1="302" y1="178" x2="429" y2="331" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stopColor="#FFFFFF" />
                    <stop offset="1" stopColor="#DFF8FF" />
                </linearGradient>
                <clipPath id="mainClip">
                    <circle cx="256" cy="256" r="170" />
                </clipPath>
                <filter id="softShadow" x="-15%" y="-15%" width="130%" height="130%">
                    <feDropShadow dx="0" dy="10" stdDeviation="12" floodColor="#0F172A" floodOpacity="0.12" />
                </filter>
            </defs>

            <g filter="url(#softShadow)">
                <circle cx="256" cy="256" r="170" fill="#FFFFFF" />
                <path d="M115 350A170 170 0 0 1 116 161" fill="none" stroke="#0B3A83" strokeWidth="18" strokeLinecap="round" />
                <path d="M398 151A170 170 0 0 1 398 361" fill="none" stroke="url(#cleanGradient)" strokeWidth="18" strokeLinecap="round" />
                <path d="M256 86A170 170 0 0 1 359 121" fill="none" stroke="#27C7E6" strokeWidth="18" strokeLinecap="round" opacity="0.9" />
                <path d="M257 426A170 170 0 0 1 360 391" fill="none" stroke="#22C55E" strokeWidth="18" strokeLinecap="round" opacity="0.95" />
            </g>

            <g clipPath="url(#mainClip)">
                <rect x="85" y="86" width="171" height="340" fill="url(#navyGradient)" opacity="0.96" />
                <rect x="256" y="86" width="171" height="340" fill="url(#cleanGradient)" opacity="0.94" />

                <g opacity="0.95">
                    <rect x="125" y="142" width="33" height="33" rx="7" fill="#F8FAFC" opacity="0.18" transform="rotate(-14 141.5 158.5)" />
                    <rect x="172" y="129" width="43" height="38" rx="8" fill="#F8FAFC" opacity="0.13" transform="rotate(9 193.5 148)" />
                    <rect x="116" y="198" width="52" height="49" rx="9" fill="#F8FAFC" opacity="0.17" transform="rotate(8 142 222.5)" />
                    <rect x="183" y="198" width="44" height="50" rx="8" fill="#F8FAFC" opacity="0.14" transform="rotate(-3 205 223)" />
                    <rect x="134" y="272" width="38" height="48" rx="8" fill="#F8FAFC" opacity="0.16" transform="rotate(-10 153 296)" />
                    <rect x="188" y="279" width="46" height="43" rx="8" fill="#F8FAFC" opacity="0.13" />
                    <rect x="122" y="352" width="27" height="27" rx="6" fill="#F8FAFC" opacity="0.17" transform="rotate(18 135.5 365.5)" />
                    <rect x="169" y="342" width="58" height="47" rx="9" fill="#F8FAFC" opacity="0.14" transform="rotate(-9 198 365.5)" />
                </g>

                <g opacity="0.95">
                    <rect x="284" y="128" width="36" height="36" rx="8" fill="#E7FBFF" opacity="0.46" />
                    <rect x="329" y="128" width="36" height="36" rx="8" fill="#E7FBFF" opacity="0.44" />
                    <rect x="374" y="128" width="36" height="36" rx="8" fill="#E7FBFF" opacity="0.42" />
                    <rect x="284" y="173" width="36" height="36" rx="8" fill="#E7FBFF" opacity="0.34" />
                    <rect x="329" y="173" width="36" height="36" rx="8" fill="#E7FBFF" opacity="0.32" />
                    <rect x="374" y="173" width="36" height="36" rx="8" fill="#E7FBFF" opacity="0.30" />
                    <rect x="284" y="218" width="36" height="36" rx="8" fill="#E7FBFF" opacity="0.28" />
                    <rect x="329" y="218" width="36" height="36" rx="8" fill="#E7FBFF" opacity="0.26" />
                    <rect x="374" y="218" width="36" height="36" rx="8" fill="#E7FBFF" opacity="0.24" />
                </g>
            </g>

            <path d="M254 93C220 151 217 196 248 249C279 301 285 354 249 419" fill="none" stroke="#FFFFFF" strokeWidth="34" strokeLinecap="round" />
            <path d="M260 94C226 150 224 197 254 248C283 298 292 350 258 417" fill="none" stroke="url(#flowGradient)" strokeWidth="17" strokeLinecap="round" />
            <path d="M241 112C218 162 222 199 250 245" fill="none" stroke="#FFFFFF" strokeWidth="7" strokeLinecap="round" opacity="0.82" />

            <g opacity="0.96">
                <circle cx="77" cy="165" r="7" fill="#0EA5E9" />
                <circle cx="88" cy="236" r="11" fill="#14B8A6" />
                <circle cx="89" cy="319" r="6" fill="#64748B" />
                <circle cx="132" cy="115" r="8" fill="#0891B2" />
                <circle cx="146" cy="414" r="7" fill="#2563EB" />
                <circle cx="210" cy="91" r="6" fill="#22C55E" />
                <rect x="62" y="276" width="14" height="14" rx="4" fill="#0F4AA2" transform="rotate(13 69 283)" />
                <rect x="102" y="102" width="20" height="20" rx="5" fill="#2563EB" transform="rotate(-16 112 112)" />
                <rect x="78" y="374" width="21" height="21" rx="5" fill="#14B8A6" transform="rotate(20 88.5 384.5)" />
                <rect x="206" y="408" width="13" height="13" rx="4" fill="#0EA5E9" transform="rotate(-14 212.5 414.5)" />
            </g>

            <g>
                <circle cx="209" cy="176" r="7" fill="#38BDF8" opacity="0.9" />
                <circle cx="221" cy="218" r="5" fill="#FFFFFF" opacity="0.65" />
                <circle cx="217" cy="307" r="6" fill="#38BDF8" opacity="0.85" />
                <circle cx="294" cy="176" r="5" fill="#E0FCFF" opacity="0.95" />
                <circle cx="309" cy="176" r="5" fill="#E0FCFF" opacity="0.90" />
                <circle cx="324" cy="176" r="5" fill="#E0FCFF" opacity="0.85" />
                <circle cx="293" cy="335" r="5" fill="#E0FCFF" opacity="0.88" />
                <circle cx="309" cy="335" r="5" fill="#E0FCFF" opacity="0.82" />
                <circle cx="325" cy="335" r="5" fill="#E0FCFF" opacity="0.78" />
            </g>

            <g>
                <rect x="279" y="277" width="145" height="109" rx="23" fill="url(#chartGradient)" stroke="#FFFFFF" strokeWidth="5" />
                <rect x="302" y="335" width="19" height="30" rx="5" fill="#2563EB" />
                <rect x="330" y="315" width="19" height="50" rx="5" fill="#0EA5E9" />
                <rect x="358" y="298" width="19" height="67" rx="5" fill="#14B8A6" />
                <rect x="386" y="322" width="19" height="43" rx="5" fill="#22C55E" />
                <path d="M301 365H410" stroke="#0B3A83" strokeWidth="5" strokeLinecap="round" opacity="0.6" />
                <path d="M305 326L333 303L365 313L403 286" fill="none" stroke="#0F4AA2" strokeWidth="8" strokeLinecap="round" strokeLinejoin="round" />
                <circle cx="305" cy="326" r="8" fill="#FFFFFF" stroke="#0F4AA2" strokeWidth="5" />
                <circle cx="333" cy="303" r="8" fill="#FFFFFF" stroke="#0F4AA2" strokeWidth="5" />
                <circle cx="365" cy="313" r="8" fill="#FFFFFF" stroke="#0F4AA2" strokeWidth="5" />
                <path d="M403 286L407 309L386 299Z" fill="#0F4AA2" />
            </g>

            <g fill="#FFFFFF" strokeWidth="7">
                <circle cx="115" cy="161" r="14" stroke="#0B3A83" />
                <circle cx="116" cy="350" r="14" stroke="#0B3A83" />
                <circle cx="256" cy="86" r="14" stroke="#27C7E6" />
                <circle cx="398" cy="151" r="14" stroke="#0BA7D6" />
                <circle cx="398" cy="361" r="14" stroke="#22C55E" />
                <circle cx="257" cy="426" r="14" stroke="#22C55E" />
            </g>
        </svg>
    );
}
