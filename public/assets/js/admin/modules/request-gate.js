function r(){let e=0;return Object.freeze({begin:()=>++e,isCurrent:t=>t===e,reset:()=>++e})}export{r as createRequestGate};
